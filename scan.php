<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: scan.php
 * LOCATIE:      /scan.php (ROOT)
 * VERSIE:       1.0
 * 
 * BESCHRIJVING: QR/Barcode Scanner Interface
 * - Camera access voor QR/Barcode scanning
 * - Hardware scanner ondersteuning
 * - Auto check-in/out bij scan
 * ═══════════════════════════════════════════════════════════════════
 */

session_start();
require_once __DIR__ . '/includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get current user
$userId = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT username, display_name, role, can_use_scanner FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user has scanner permission
if (!$currentUser['can_use_scanner'] && $currentUser['role'] !== 'superadmin') {
    die('
        <html>
        <head><title>Geen Toegang</title></head>
        <body style="font-family: Arial; text-align: center; padding: 50px;">
            <h1>⛔ Geen Toegang</h1>
            <p>Je hebt geen toestemming om de scanner te gebruiken.</p>
            <p>Neem contact op met een administrator.</p>
            <a href="/" style="color: #007acc;">← Terug naar home</a>
        </body>
        </html>
    ');
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📷 QR/Barcode Scanner - PeopleDisplay</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 24px;
            color: #2c3e50;
        }
        
        .header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-back {
            background: #718096;
            color: white;
        }
        
        .btn-back:hover {
            background: #4a5568;
        }
        
        .container {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            gap: 20px;
        }
        
        .scanner-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
        }
        
        .scanner-card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .video-container {
            position: relative;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
            display: none;
            min-height: 400px; /* Groter! */
        }
        
        .video-container.active {
            display: block;
        }
        
        .video-container.fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            border-radius: 0;
            z-index: 9999;
            margin: 0;
        }
        
        #scanner-video {
            width: 100%;
            height: 100%;
            object-fit: cover; /* Fill entire container */
            display: block;
        }
        
        .scan-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            height: 60%;
            border: 3px solid #4fc3f7;
            border-radius: 12px;
            box-shadow: 0 0 0 9999px rgba(0,0,0,0.5);
            pointer-events: none;
        }
        
        .scan-overlay::before,
        .scan-overlay::after {
            content: '';
            position: absolute;
            width: 40px;
            height: 40px;
            border: 4px solid #4fc3f7;
        }
        
        .scan-overlay::before {
            top: -4px;
            left: -4px;
            border-right: none;
            border-bottom: none;
        }
        
        .scan-overlay::after {
            bottom: -4px;
            right: -4px;
            border-left: none;
            border-top: none;
        }
        
        .scanner-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: #4fc3f7;
            top: 0;
            animation: scan 2s linear infinite;
        }
        
        @keyframes scan {
            0% { top: 0; }
            50% { top: 100%; }
            100% { top: 0; }
        }
        
        .controls {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #d32f2f;
        }
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .status-message {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .status-message.active {
            display: block;
        }
        
        .status-message.info {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-message.success {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .status-message.error {
            background: #ffebee;
            color: #c62828;
        }
        
        .status-message.warning {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .device-info {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .device-info h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .device-list {
            list-style: none;
        }
        
        .device-list li {
            padding: 8px;
            margin: 5px 0;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .device-list li.active {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
        }
        
        .manual-input {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }
        
        .manual-input h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .manual-input input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .manual-input input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .recent-scans {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }
        
        .recent-scans h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .scan-item {
            padding: 10px;
            background: #f5f5f5;
            border-radius: 6px;
            margin: 5px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .scan-item.success {
            background: #e8f5e9;
        }
        
        .scan-item.error {
            background: #ffebee;
        }
        
        @media (max-width: 768px) {
            .scanner-card {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 18px;
            }
            
            .btn {
                padding: 8px 16px;
                font-size: 12px;
            }
            
            .video-container {
                min-height: 300px;
            }
        }
        
        /* Success Popup Modal */
        .success-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            animation: fadeIn 0.3s;
        }
        
        .success-modal.active {
            display: flex;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .success-modal-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            animation: scaleIn 0.3s;
        }
        
        @keyframes scaleIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        .success-modal-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .success-modal-icon.success {
            animation: bounce 0.6s;
        }
        
        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .success-modal h2 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .success-modal .employee-name {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .success-modal .status-badge {
            display: inline-block;
            padding: 10px 30px;
            border-radius: 25px;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .success-modal .status-badge.in {
            background: #4caf50;
            color: white;
        }
        
        .success-modal .status-badge.out {
            background: #f44336;
            color: white;
        }
        
        .success-modal .close-timer {
            color: #999;
            font-size: 14px;
        }
        
        .fullscreen-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 20px;
            z-index: 10;
            transition: all 0.3s;
        }
        
        .fullscreen-btn:hover {
            background: rgba(0, 0, 0, 0.8);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📷 QR/Barcode Scanner</h1>
        <div class="user-info">
            <span>👤 <?= htmlspecialchars($currentUser['display_name'] ?: $currentUser['username']) ?></span>
            <a href="/" class="btn btn-back">← Terug</a>
        </div>
    </div>
    
    <div class="container">
        <div class="scanner-card">
            <h2>Scan QR-code of Barcode</h2>
            
            <!-- Status Message -->
            <div id="status-message" class="status-message"></div>
            
            <!-- Device Info -->
            <div id="device-info" class="device-info" style="display: none;">
                <h3>🎥 Beschikbare Camera's:</h3>
                <ul id="device-list" class="device-list"></ul>
            </div>
            
            <!-- Video Container -->
            <div id="video-container" class="video-container">
                <button id="btn-fullscreen" class="fullscreen-btn" title="Volledig scherm">⛶</button>
                <video id="scanner-video" playsinline autoplay></video>
                <div class="scan-overlay">
                    <div class="scanner-line"></div>
                </div>
            </div>
            
            <!-- Controls -->
            <div class="controls">
                <button id="btn-start" class="btn btn-primary">📷 Start Camera</button>
                <button id="btn-stop" class="btn btn-danger" style="display: none;">⏹️ Stop Camera</button>
                <button id="btn-switch" class="btn btn-success" style="display: none;">🔄 Wissel Camera</button>
            </div>
            
            <!-- Manual Input -->
            <div class="manual-input">
                <h3>⌨️ Handmatige Invoer</h3>
                <input type="text" id="manual-code" placeholder="Scan of typ employee ID..." autofocus>
                <button id="btn-manual-submit" class="btn btn-primary" style="width: 100%;">✓ Verwerken</button>
            </div>
            
            <!-- Recent Scans -->
            <div class="recent-scans">
                <h3>📋 Recente Scans</h3>
                <div id="recent-scans-list"></div>
            </div>
        </div>
    </div>
    
    <!-- Success Modal -->
    <div id="success-modal" class="success-modal">
        <div class="success-modal-content">
            <div class="success-modal-icon">✅</div>
            <h2>Scan Gelukt!</h2>
            <div class="employee-name" id="modal-employee-name"></div>
            <div class="status-badge" id="modal-status-badge"></div>
            <div class="close-timer">Sluit automatisch over <span id="modal-countdown">3</span>s</div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script>
        // ═══════════════════════════════════════════════════════════════════
        // QR/BARCODE SCANNER v1.0
        // ═══════════════════════════════════════════════════════════════════
        
        const video = document.getElementById('scanner-video');
        const videoContainer = document.getElementById('video-container');
        const statusMessage = document.getElementById('status-message');
        const deviceInfo = document.getElementById('device-info');
        const deviceList = document.getElementById('device-list');
        const recentScansList = document.getElementById('recent-scans-list');
        const manualCodeInput = document.getElementById('manual-code');
        
        const btnStart = document.getElementById('btn-start');
        const btnStop = document.getElementById('btn-stop');
        const btnSwitch = document.getElementById('btn-switch');
        const btnManualSubmit = document.getElementById('btn-manual-submit');
        const btnFullscreen = document.getElementById('btn-fullscreen');
        
        const successModal = document.getElementById('success-modal');
        const modalEmployeeName = document.getElementById('modal-employee-name');
        const modalStatusBadge = document.getElementById('modal-status-badge');
        const modalCountdown = document.getElementById('modal-countdown');
        
        let stream = null;
        let scanning = false;
        let devices = [];
        let currentDeviceIndex = 0;
        let recentScans = [];
        let lastScannedCode = null;
        let lastScanTime = 0;
        
        // Audio context for beep sounds
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        
        // Initialize
        init();
        
        async function init() {
            showStatus('Controleren van camera toegang...', 'info');
            
            // Check if mediaDevices is supported
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showStatus('⚠️ Camera API niet ondersteund in deze browser', 'error');
                return;
            }
            
            // Detect available cameras
            await detectCameras();
            
            // Enable manual input listener
            manualCodeInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    processManualCode();
                }
            });
            
            btnManualSubmit.addEventListener('click', processManualCode);
        }
        
        async function detectCameras() {
            try {
                // Request permission first
                await navigator.mediaDevices.getUserMedia({ video: true });
                
                // Get all devices
                const allDevices = await navigator.mediaDevices.enumerateDevices();
                devices = allDevices.filter(device => device.kind === 'videoinput');
                
                console.log('📷 Cameras gevonden:', devices.length);
                
                if (devices.length === 0) {
                    showStatus('⚠️ Geen camera gevonden', 'warning');
                    return;
                }
                
                // Show device list
                deviceInfo.style.display = 'block';
                deviceList.innerHTML = devices.map((device, index) => `
                    <li class="${index === 0 ? 'active' : ''}">
                        📷 ${device.label || `Camera ${index + 1}`}
                    </li>
                `).join('');
                
                showStatus(`✅ ${devices.length} camera(s) gevonden - klik "Start Camera"`, 'success');
                
                // Show switch button if multiple cameras
                if (devices.length > 1) {
                    btnSwitch.style.display = 'inline-block';
                }
                
            } catch (error) {
                console.error('Camera detectie fout:', error);
                showStatus('❌ Geen toegang tot camera: ' + error.message, 'error');
            }
        }
        
        async function startCamera() {
            if (devices.length === 0) {
                showStatus('⚠️ Geen camera beschikbaar', 'warning');
                return;
            }
            
            try {
                const deviceId = devices[currentDeviceIndex].deviceId;
                
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        deviceId: deviceId ? { exact: deviceId } : undefined,
                        facingMode: 'environment' // Prefer back camera on mobile
                    }
                });
                
                video.srcObject = stream;
                videoContainer.classList.add('active');
                
                btnStart.style.display = 'none';
                btnStop.style.display = 'inline-block';
                
                showStatus('📷 Camera actief - houd QR-code voor de camera', 'success');
                
                // Start scanning
                scanning = true;
                requestAnimationFrame(scan);
                
            } catch (error) {
                console.error('Camera start fout:', error);
                showStatus('❌ Camera starten mislukt: ' + error.message, 'error');
            }
        }
        
        function stopCamera() {
            scanning = false;
            
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            
            video.srcObject = null;
            videoContainer.classList.remove('active');
            
            btnStart.style.display = 'inline-block';
            btnStop.style.display = 'none';
            
            showStatus('⏹️ Camera gestopt', 'info');
        }
        
        function switchCamera() {
            currentDeviceIndex = (currentDeviceIndex + 1) % devices.length;
            
            // Update active device in list
            deviceList.querySelectorAll('li').forEach((li, index) => {
                li.classList.toggle('active', index === currentDeviceIndex);
            });
            
            // Restart camera with new device
            if (scanning) {
                stopCamera();
                setTimeout(startCamera, 100);
            }
        }
        
        function scan() {
            if (!scanning) return;
            
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.height = video.videoHeight;
                canvas.width = video.videoWidth;
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: 'dontInvert'
                });
                
                if (code) {
                    handleScan(code.data);
                }
            }
            
            requestAnimationFrame(scan);
        }
        
        async function handleScan(code) {
            // Debounce - prevent duplicate scans
            const now = Date.now();
            if (code === lastScannedCode && (now - lastScanTime) < 3000) {
                return;
            }
            
            lastScannedCode = code;
            lastScanTime = now;
            
            console.log('📷 Code gescand:', code);
            showStatus('✅ Code gescand: ' + code, 'success');
            
            // Vibrate if supported
            if (navigator.vibrate) {
                navigator.vibrate(200);
            }
            
            // Process the code
            await processCode(code);
        }
        
        async function processCode(code) {
            try {
                // Call API to process employee code
                const response = await fetch('/api/process_scan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ code: code })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showStatus(`✅ ${data.message}`, 'success');
                    addRecentScan(code, true, data.message);
                    
                    // Play SUCCESS sound
                    playBeep('success');
                    
                    // Show success modal
                    showSuccessModal(data.employee.name, data.employee.new_status);
                    
                    // Vibrate
                    if (navigator.vibrate) {
                        navigator.vibrate([200, 100, 200]);
                    }
                } else {
                    showStatus(`❌ ${data.error}`, 'error');
                    addRecentScan(code, false, data.error);
                    
                    // Play ERROR sound
                    playBeep('error');
                }
                
            } catch (error) {
                console.error('Process error:', error);
                showStatus('❌ Fout bij verwerken: ' + error.message, 'error');
                addRecentScan(code, false, 'Verwerkingsfout');
                playBeep('error');
            }
        }
        
        function processManualCode() {
            const code = manualCodeInput.value.trim();
            
            if (!code) {
                showStatus('⚠️ Voer een code in', 'warning');
                return;
            }
            
            processCode(code);
            manualCodeInput.value = '';
        }
        
        function addRecentScan(code, success, message) {
            const scan = {
                code: code,
                success: success,
                message: message,
                time: new Date().toLocaleTimeString()
            };
            
            recentScans.unshift(scan);
            if (recentScans.length > 5) {
                recentScans = recentScans.slice(0, 5);
            }
            
            renderRecentScans();
        }
        
        function renderRecentScans() {
            if (recentScans.length === 0) {
                recentScansList.innerHTML = '<p style="color: #999; text-align: center;">Nog geen scans</p>';
                return;
            }
            
            recentScansList.innerHTML = recentScans.map(scan => `
                <div class="scan-item ${scan.success ? 'success' : 'error'}">
                    <div>
                        <strong>${scan.code}</strong><br>
                        <small>${scan.message}</small>
                    </div>
                    <small>${scan.time}</small>
                </div>
            `).join('');
        }
        
        function showStatus(message, type) {
            statusMessage.textContent = message;
            statusMessage.className = 'status-message active ' + type;
            
            // Auto-hide after 5 seconds (except errors)
            if (type !== 'error') {
                setTimeout(() => {
                    statusMessage.classList.remove('active');
                }, 5000);
            }
        }
        
        function showSuccessModal(employeeName, status) {
            modalEmployeeName.textContent = employeeName;
            modalStatusBadge.textContent = status === 'IN' ? '✓ INGECHECKT' : '✗ UITGECHECKT';
            modalStatusBadge.className = 'status-badge ' + (status === 'IN' ? 'in' : 'out');
            
            successModal.classList.add('active');
            
            // Auto-close countdown
            let countdown = 3;
            modalCountdown.textContent = countdown;
            
            const countdownInterval = setInterval(() => {
                countdown--;
                modalCountdown.textContent = countdown;
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    successModal.classList.remove('active');
                }
            }, 1000);
            
            // Click to close early
            successModal.onclick = () => {
                clearInterval(countdownInterval);
                successModal.classList.remove('active');
            };
        }
        
        function playBeep(type) {
            if (!audioContext) return;
            
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            if (type === 'success') {
                // Happy double beep: 800Hz → 1000Hz
                oscillator.frequency.value = 800;
                oscillator.start(audioContext.currentTime);
                oscillator.frequency.setValueAtTime(1000, audioContext.currentTime + 0.1);
                oscillator.stop(audioContext.currentTime + 0.2);
                
                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
            } else {
                // Error beep: 400Hz lower tone
                oscillator.frequency.value = 400;
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.3);
                
                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
            }
        }
        
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                videoContainer.requestFullscreen().catch(err => {
                    console.log('Fullscreen error:', err);
                });
                videoContainer.classList.add('fullscreen');
                btnFullscreen.textContent = '⛶';
            } else {
                document.exitFullscreen();
                videoContainer.classList.remove('fullscreen');
                btnFullscreen.textContent = '⛶';
            }
        }
        
        function playSound(type) {
            // Deprecated - use playBeep instead
        }
        
        // Event listeners
        btnStart.addEventListener('click', startCamera);
        btnStop.addEventListener('click', stopCamera);
        btnSwitch.addEventListener('click', switchCamera);
        btnFullscreen.addEventListener('click', toggleFullscreen);
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        });
        
        // Initialize recent scans display
        renderRecentScans();
    </script>
</body>
</html>
