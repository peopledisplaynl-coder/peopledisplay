<?php
// docs/tools/http-fixtures.php
// Only for the demo/screenshot environment (php -d auto_prepend_file=…): the infoscreen
// fetches pages of the fictional *.voorbeeld.test hosts from tests/fixtures instead of
// the internet. Never uploaded to a server (docs/ is a developer folder).

function pd_infoscreen_http_get_override(string $url): array
{
    $dir = dirname(__DIR__, 2) . '/tests/fixtures/infoscreen/';
    $map = [
        'https://www.voorbeeldschool.test/actueel' => 'web/schoolsite.html',
        'https://gemeente.voorbeeld.test/nieuws'   => 'web/articles.html',
        'https://www.stichting.test/vacatures'     => 'web/webflow.html',
        'https://kc.voorbeeld.test/'               => 'web/wordpress.html',
        'https://kc.voorbeeld.test/feed/'          => 'rss2.xml',
        'https://app.voorbeeld.test/'              => 'web/jsonly.html',
    ];
    $key = rtrim($url, '/') === rtrim('https://kc.voorbeeld.test/', '/') ? 'https://kc.voorbeeld.test/' : $url;
    if (isset($map[$key]) && is_file($dir . $map[$key])) {
        return ['ok' => true, 'status' => 200, 'body' => file_get_contents($dir . $map[$key]), 'error' => ''];
    }
    return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Niet bereikbaar in de demo-omgeving'];
}
