<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
// Badge Generator v2.1 FINAL - All templates fixed + logo support
// Upload naar: /api/generate_badges.php

error_reporting(E_ALL);
ini_set('display_errors', 0);
$logFile = __DIR__ . '/badge_generation.log';

function logError($msg) { global $logFile; error_log(date('[Y-m-d H:i:s] ').$msg."\n",3,$logFile); }
function jsonError($msg,$code=500) { logError("ERROR: $msg"); http_response_code($code); die(json_encode(['success'=>false,'error'=>$msg])); }

try {
    session_start();
    require_once __DIR__.'/../includes/db.php';
    if(!isset($_SESSION['user_id'])) jsonError('Niet ingelogd',401);
    
    $stmt=$db->prepare("SELECT role FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $user=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!in_array($user['role'],['admin','superadmin'])) jsonError('Geen toegang',403);
    
    $data=json_decode(file_get_contents('php://input'),true);
    if(!isset($data['employee_ids'])||empty($data['employee_ids'])) jsonError('Geen employees',400);
    
    $ids=array_map('intval',$data['employee_ids']);
    $codeType=$data['code_type']??'qr';
    $template=$data['template']??'professional';
    $logoData=$data['logo']??null;
    
    // Convert base64 logo to temp file
    $logo=null;
    if($logoData && strpos($logoData,'data:image')===0){
        $logoDir=__DIR__.'/../tmp/badge_logos/';
        if(!is_dir($logoDir))@mkdir($logoDir,0755,true);
        
        // Extract base64 part
        $parts=explode(',',$logoData);
        if(count($parts)===2){
            $decoded=base64_decode($parts[1]);
            if($decoded!==false){
                $logo=$logoDir.'logo_'.md5($logoData).'.png';
                file_put_contents($logo,$decoded);
                logError("Logo saved to: $logo");
            }
        }
    }
    
    logError(count($ids)." badges - $template - $codeType".($logo?" - with logo":""));
    
    $ph=str_repeat('?,',count($ids)-1).'?';
    $stmt=$db->prepare("SELECT id,employee_id,naam,voornaam,achternaam,functie,afdeling,locatie,foto_url,bhv FROM employees WHERE id IN($ph) AND actief=1 ORDER BY naam");
    $stmt->execute($ids);
    $emps=$stmt->fetchAll(PDO::FETCH_ASSOC);
    if(empty($emps)) jsonError('Geen employees gevonden',404);
    
    $paths=[__DIR__.'/../vendor/tecnickcom/tcpdf/tcpdf.php',__DIR__.'/../tcpdf/tcpdf.php',__DIR__.'/../../tcpdf/tcpdf.php'];
    $tcpdf=null;
    foreach($paths as $p) if(file_exists($p)){$tcpdf=$p;break;}
    if(!$tcpdf) jsonError('TCPDF niet gevonden',500);
    
    require_once($tcpdf);
    $pdf=new TCPDF('P','mm','A4',true,'UTF-8',false);
    $pdf->SetCreator('PeopleDisplay');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10,10,10);
    $pdf->SetAutoPageBreak(false);
    
    $w=85.6;$h=54;$sh=9;$sv=3;$cnt=0;
    foreach($emps as $emp){
        if($cnt%10===0) $pdf->AddPage();
        $col=($cnt%10)%2;
        $row=floor(($cnt%10)/2);
        $x=10+($col*($w+$sh));
        $y=10+($row*($h+$sv));
        drawBadge($pdf,$emp,$x,$y,$w,$h,$template,$codeType,$logo);
        $cnt++;
    }
    
    logError("$cnt badges OK");
    $pdf->Output('badges_'.date('Ymd_His').'.pdf','D');
    exit;
} catch(Exception $e){
    logError("EX: ".$e->getMessage());
    jsonError('PDF fout: '.$e->getMessage(),500);
}

function drawBadge($pdf,$e,$x,$y,$w,$h,$t,$c,$logo){
    switch($t){
        case 'colorful': drawColorful($pdf,$e,$x,$y,$w,$h,$c,$logo); break;
        case 'minimalist': drawMinimal($pdf,$e,$x,$y,$w,$h,$c,$logo); break;
        case 'emergency': drawEmergency($pdf,$e,$x,$y,$w,$h,$c,$logo); break;
        default: drawProfessional($pdf,$e,$x,$y,$w,$h,$c,$logo);
    }
}

function drawProfessional($pdf,$e,$x,$y,$w,$h,$c,$logo){
    $pdf->SetFillColor(100,120,220);
    $pdf->RoundedRect($x,$y,$w,$h,2,'1111','F');
    
    // Foto positie en grootte
    $ps=16;  // Foto diameter
    $px=$x+5;
    $py=$y+($logo?14:5);  // Lager als er logo is
    
    // Logo boven foto met witte cirkel achtergrond
    if($logo){
        $logoSize=10;
        $logoCenterX=$px+$ps/2;  // Uitlijnen met foto center
        $logoY=$y+3;
        
        // Witte cirkel achter logo (iets groter dan logo)
        $pdf->SetFillColor(255,255,255);
        $pdf->SetAlpha(0.95);
        $pdf->Circle($logoCenterX,$logoY+$logoSize/2,$logoSize/2+1,0,360,'F');
        $pdf->SetAlpha(1);
        
        // Logo (behoud aspect ratio, geen squeeze)
        loadImgCentered($pdf,$logo,$logoCenterX,$logoY+$logoSize/2,$logoSize);
    }
    
    // Witte cirkel voor foto (achtergrond)
    $pdf->SetFillColor(255,255,255);
    $pdf->SetAlpha(0.95);
    $pdf->Circle($px+$ps/2,$py+$ps/2,$ps/2+1,0,360,'F');
    $pdf->SetAlpha(1);
    
    // Foto met cirkel mask
    if(!empty($e['foto_url'])){
        $pdf->StartTransform();
        $pdf->Circle($px+$ps/2,$py+$ps/2,$ps/2,0,360,'CNZ');
        loadPhoto($pdf,$e,$px,$py,$ps);
        $pdf->StopTransform();
    }
    
    // Witte info box
    $pdf->SetFillColor(255,255,255);
    $pdf->SetAlpha(0.95);
    $pdf->RoundedRect($x+$ps+8,$y+3,$w-$ps-11,20,2,'1111','F');
    $pdf->SetAlpha(1);
    
    $n=getName($e);
    $pdf->SetTextColor(45,55,72);
    $pdf->SetFont('helvetica','B',10);
    $pdf->SetXY($x+$ps+10,$y+5);
    $pdf->Cell($w-$ps-13,4,$n,0,0,'L');
    
    if($e['functie']){
        $pdf->SetTextColor(100,116,139);
        $pdf->SetFont('helvetica','',8);
        $pdf->SetXY($x+$ps+10,$y+10);
        $pdf->Cell($w-$ps-13,3,$e['functie'],0,0,'L');
    }
    
    if($e['locatie']){
        $pdf->SetTextColor(148,163,184);
        $pdf->SetFont('helvetica','',7);
        $pdf->SetXY($x+$ps+10,$y+15);
        $pdf->Cell($w-$ps-13,3,$e['locatie'],0,0,'L');
    }
    
    $fy=$y+$h-18;
    $pdf->SetFillColor(255,255,255);
    $pdf->SetAlpha(0.95);
    $pdf->Rect($x,$fy,$w,18,'F');
    $pdf->SetAlpha(1);
    
    drawCodes($pdf,$e,$x,$fy+2,$w,14,$c);
    
    if($e['bhv']==='Ja'){
        $pdf->SetFillColor(220,38,38);
        $pdf->RoundedRect($x+$w-18,$y+2,16,4,1,'1111','F');
        $pdf->SetTextColor(255,255,255);
        $pdf->SetFont('helvetica','B',6);
        $pdf->SetXY($x+$w-18,$y+2.5);
        $pdf->Cell(16,3,'BHV',0,0,'C');
    }
    
    guides($pdf,$x,$y,$w,$h);
}

function drawColorful($pdf,$e,$x,$y,$w,$h,$c,$logo){
    $pdf->SetFillColor(240,147,251);
    $pdf->RoundedRect($x,$y,$w,$h,3,'1111','F');
    
    // Decoratie (subtiel)
    $pdf->SetAlpha(0.1);
    $pdf->SetFillColor(255,255,255);
    for($i=0;$i<5;$i++) $pdf->Circle($x+10+($i*15),$y+5,8,0,360,'F');
    $pdf->SetAlpha(1);
    
    // Foto positie en grootte
    $ps=18;
    $px=$x+5;
    $py=$y+($logo?14:5);
    
    // Logo boven foto met witte cirkel achtergrond (zoals Professional)
    if($logo){
        $logoSize=10;
        $logoCenterX=$px+$ps/2;
        $logoY=$y+3;
        
        $pdf->SetFillColor(255,255,255);
        $pdf->SetAlpha(0.95);
        $pdf->Circle($logoCenterX,$logoY+$logoSize/2,$logoSize/2+1,0,360,'F');
        $pdf->SetAlpha(1);
        
        loadImgCentered($pdf,$logo,$logoCenterX,$logoY+$logoSize/2,$logoSize);
    }
    
    // Witte cirkel voor foto
    $pdf->SetFillColor(255,255,255);
    $pdf->SetAlpha(0.95);
    $pdf->Circle($px+$ps/2,$py+$ps/2,$ps/2+1,0,360,'F');
    $pdf->SetAlpha(1);
    
    // Foto met cirkel mask (zoals Professional)
    if(!empty($e['foto_url'])){
        $pdf->StartTransform();
        $pdf->Circle($px+$ps/2,$py+$ps/2,$ps/2,0,360,'CNZ');
        loadPhoto($pdf,$e,$px,$py,$ps);
        $pdf->StopTransform();
    }
    
    // Witte info box voor naam/functie (zoals Professional)
    $pdf->SetFillColor(255,255,255);
    $pdf->SetAlpha(0.95);
    $pdf->RoundedRect($x+$ps+8,$y+3,$w-$ps-11,22,2,'1111','F');
    $pdf->SetAlpha(1);
    
    // Naam (donker in wit vak)
    $n=getName($e);
    $pdf->SetTextColor(236,72,153);  // Roze kleur voor accent
    $pdf->SetFont('helvetica','B',11);
    $pdf->SetXY($x+$ps+10,$y+5);
    $pdf->Cell($w-$ps-13,5,$n,0,0,'L');
    
    // Functie
    if($e['functie']){
        $pdf->SetTextColor(100,116,139);
        $pdf->SetFont('helvetica','',8);
        $pdf->SetXY($x+$ps+10,$y+11);
        $pdf->Cell($w-$ps-13,4,$e['functie'],0,0,'L');
    }
    
    // Locatie
    if($e['locatie']){
        $pdf->SetTextColor(148,163,184);
        $pdf->SetFont('helvetica','',7);
        $pdf->SetXY($x+$ps+10,$y+16);
        $pdf->Cell($w-$ps-13,3,$e['locatie'],0,0,'L');
    }
    
    // Witte footer voor QR/Barcode (scanbaar!)
    $fy=$y+$h-18;
    $pdf->SetFillColor(255,255,255);
    $pdf->SetAlpha(0.95);
    $pdf->Rect($x,$fy,$w,18,'F');
    $pdf->SetAlpha(1);
    
    // QR/Barcode ZWART in wit vak (scanbaar)
    drawCodes($pdf,$e,$x,$fy+2,$w,14,$c,false);  // false = zwarte codes!
    
    guides($pdf,$x,$y,$w,$h);
}

function drawMinimal($pdf,$e,$x,$y,$w,$h,$c,$logo){
    $pdf->SetFillColor(255,255,255);
    $pdf->SetDrawColor(0,0,0);
    $pdf->SetLineWidth(0.5);
    $pdf->RoundedRect($x,$y,$w,$h,2,'1111','DF');
    
    $topY=$y+3;
    if($logo){
        loadImg($pdf,$logo,$x+($w-12)/2,$topY,12);
        $topY+=15;
    }else{
        $topY+=5;
    }
    
    $n=strtoupper(getName($e));
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('helvetica','B',11);
    $pdf->SetXY($x,$topY);
    $pdf->Cell($w,5,$n,0,0,'C');
    
    if($e['functie']){
        $pdf->SetTextColor(80,80,80);
        $pdf->SetFont('helvetica','',8);
        $pdf->SetXY($x,$topY+6);
        $pdf->Cell($w,4,$e['functie'],0,0,'C');
    }
    
    $pdf->SetTextColor(120,120,120);
    $pdf->SetFont('helvetica','',7);
    $pdf->SetXY($x,$topY+11);
    $pdf->Cell($w,3,$e['employee_id'],0,0,'C');
    
    $codeY=$y+$h-22;
    drawCodes($pdf,$e,$x,$codeY,$w,20,$c);
    guides($pdf,$x,$y,$w,$h);
}

function drawEmergency($pdf,$e,$x,$y,$w,$h,$c,$logo){
    $pdf->SetFillColor(255,255,255);
    $pdf->SetDrawColor(220,38,38);
    $pdf->SetLineWidth(1);
    $pdf->RoundedRect($x,$y,$w,$h,2,'1111','DF');
    
    $pdf->SetFillColor(220,38,38);
    $pdf->Rect($x,$y,$w,8,'F');
    $pdf->SetTextColor(255,255,255);
    $pdf->SetFont('helvetica','B',9);
    $pdf->SetXY($x,$y+2);
    $pdf->Cell($w,5,'BHV MEDEWERKER',0,0,'C');
    
    if($logo) loadImg($pdf,$logo,$x+3,$y+1,6);
    
    $ps=16;$px=$x+($w-$ps)/2;$py=$y+10;
    loadPhoto($pdf,$e,$px,$py,$ps);
    
    $n=getName($e);
    $pdf->SetTextColor(220,38,38);
    $pdf->SetFont('helvetica','B',10);
    $pdf->SetXY($x,$py+$ps+1);
    $pdf->Cell($w,5,$n,0,0,'C');
    
    if($e['functie']){
        $pdf->SetTextColor(0,0,0);
        $pdf->SetFont('helvetica','',8);
        $pdf->SetXY($x,$py+$ps+6);
        $pdf->Cell($w,4,$e['functie'],0,0,'C');
    }
    
    $codeY=$y+$h-18;
    drawCodes($pdf,$e,$x,$codeY,$w,16,$c);
    guides($pdf,$x,$y,$w,$h);
}

function loadPhoto($pdf,$e,$x,$y,$s){
    if(empty($e['foto_url'])) return;
    try{
        $p=$e['foto_url'];
        if(strpos($p,'http')===0){
            $d=__DIR__.'/../tmp/badge_photos/';
            if(!is_dir($d))@mkdir($d,0755,true);
            $f=$d.'emp_'.$e['id'].'_'.md5($p).'.jpg';
            if(!file_exists($f)){
                $img=@file_get_contents($p);
                if($img!==false) file_put_contents($f,$img);
            }
            if(file_exists($f)) $pdf->Image($f,$x,$y,$s,$s,'','','',false,300,'',false,false,1,false,false,false);
        }else{
            $paths=[$p,__DIR__.'/../'.$p,__DIR__.'/../../'.$p,__DIR__.'/../uploads/'.basename($p),$_SERVER['DOCUMENT_ROOT'].$p];
            foreach($paths as $tp) if(file_exists($tp)&&is_file($tp)){$pdf->Image($tp,$x,$y,$s,$s,'','','',false,300,'',false,false,1,false,false,false);break;}
        }
    }catch(Exception $ex){}
}

function loadImg($pdf,$path,$x,$y,$s){
    try{
        $paths=[$path,__DIR__.'/../'.$path,__DIR__.'/../../'.$path,__DIR__.'/../uploads/logos/'.basename($path),$_SERVER['DOCUMENT_ROOT'].$path];
        foreach($paths as $p) if(file_exists($p)&&is_file($p)){$pdf->Image($p,$x,$y,$s,$s,'','','',false,300,'',false,false,1,false,false,false);break;}
    }catch(Exception $ex){}
}

function loadImgCentered($pdf,$path,$centerX,$centerY,$maxSize){
    try{
        $paths=[$path,__DIR__.'/../'.$path,__DIR__.'/../../'.$path,__DIR__.'/../uploads/logos/'.basename($path),$_SERVER['DOCUMENT_ROOT'].$path];
        
        foreach($paths as $p){
            if(file_exists($p)&&is_file($p)){
                // Get image dimensions
                $info=@getimagesize($p);
                if($info!==false){
                    $imgW=$info[0];
                    $imgH=$info[1];
                    $aspect=$imgW/$imgH;
                    
                    // Calculate size maintaining aspect ratio
                    if($aspect>1){
                        // Landscape
                        $w=$maxSize;
                        $h=$maxSize/$aspect;
                    }else{
                        // Portrait or square
                        $h=$maxSize;
                        $w=$maxSize*$aspect;
                    }
                    
                    // Center position
                    $x=$centerX-$w/2;
                    $y=$centerY-$h/2;
                    
                    $pdf->Image($p,$x,$y,$w,$h,'','','',false,300,'',false,false,1,false,false,false);
                }
                break;
            }
        }
    }catch(Exception $ex){}
}

function getName($e){
    return (!empty($e['voornaam'])&&!empty($e['achternaam'])) ? $e['voornaam'].' '.$e['achternaam'] : $e['naam'];
}

function drawCodes($pdf,$e,$x,$y,$w,$h,$t,$white=false){
    $fg=$white?[255,255,255]:[0,0,0];
    $s=['border'=>0,'vpadding'=>0,'hpadding'=>0,'fgcolor'=>$fg,'bgcolor'=>false];
    
    if($t==='qr'){
        $sz=min(16,$h-2);
        $qx=$x+($w-$sz)/2;
        $qy=$y+($h-$sz)/2;
        try{$pdf->write2DBarcode($e['employee_id'],'QRCODE,L',$qx,$qy,$sz,$sz,$s,'N');}catch(Exception $ex){}
    }elseif($t==='barcode'){
        $bw=min(55,$w-10);
        $bh=min(12,$h-2);
        $bx=$x+($w-$bw)/2;
        $by=$y+($h-$bh)/2;
        try{$pdf->write1DBarcode($e['employee_id'],'C128',$bx,$by,$bw,$bh);}catch(Exception $ex){}
    }elseif($t==='both'){
        $qsz=min(13,$h-2);
        $qx=$x+8;
        $qy=$y+($h-$qsz)/2;
        try{$pdf->write2DBarcode($e['employee_id'],'QRCODE,L',$qx,$qy,$qsz,$qsz,$s,'N');}catch(Exception $ex){}
        
        $bw=min(40,$w-$qsz-20);
        $bh=min(10,$h-2);
        $bx=$x+$w-$bw-8;
        $by=$y+($h-$bh)/2;
        try{$pdf->write1DBarcode($e['employee_id'],'C128',$bx,$by,$bw,$bh);}catch(Exception $ex){}
    }
}

function guides($pdf,$x,$y,$w,$h){
    $pdf->SetDrawColor(200,200,200);
    $pdf->SetLineWidth(0.1);
    $m=1.5;
    $pdf->Line($x-$m,$y,$x,$y);$pdf->Line($x,$y-$m,$x,$y);
    $pdf->Line($x+$w,$y,$x+$w+$m,$y);$pdf->Line($x+$w,$y-$m,$x+$w,$y);
    $pdf->Line($x-$m,$y+$h,$x,$y+$h);$pdf->Line($x,$y+$h,$x,$y+$h+$m);
    $pdf->Line($x+$w,$y+$h,$x+$w+$m,$y+$h);$pdf->Line($x+$w,$y+$h,$x,$y+$h+$m);
}
