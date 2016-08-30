<?php

require_once('../lib/satoshipay/src/Receipt.php');

if (getEnvironment('DEBUG') == '1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    if (getEnvironment('ERROR_LOG') != '') {
        ini_set('error_log', getEnvironment('ERROR_LOG'));
    }
}

if (getEnvironment('PATH_PREFIX')) {
    $pathPrefix = getEnvironment('PATH_PREFIX');
} else {
    $pathPrefix = '../files/';
}

function debug($message) {
    if (getEnvironment('DEBUG') != '1') {
        return;
    }
    error_log($message);
}

function getEnvironment($name) {
    if (isset($_ENV[$name])) {
        return $_ENV[$name];
    }

    // Support SetEnv in .htaccess with mod_rewrite instructions
    if (isset($_ENV['REDIRECT_' . $name])) {
        return $_ENV['REDIRECT_' . $name];
    }

    return '';
}

function notFound() {
    http_response_code(404);
    echo "Not Found\n";
    exit();
}

function paymentRequired() {
    http_response_code(402);
    echo "Payment Required\n";
    exit();
}

function findFile($name) {
    global $pathPrefix;
    $filePath = $pathPrefix . $name . '/' . $name;
    if (!file_exists($filePath) || !file_exists(getMetadataPath($name))) {
        return false;
    }
    return $filePath;
}

function getMetadataPath($name) {
    global $pathPrefix;
    return $pathPrefix . $name . '/metadata.ini';
}

function getMetadata($file, $field = false) {
    $metadata = parse_ini_file(getMetadataPath($file), true);
    if ($field === false) {
        return $metadata;
    }
    if (!isset($metadata[$field])) {
        return false;
    }
    return $metadata[$field];
}

function getSecret($file) {
    return getMetadata($file, 'secret');
}

function sendFile($fileName) {
    $pathParts = pathinfo($fileName);
    $fileBasename = $pathParts['basename'];

    $filePath = findFile($fileBasename);

    if (getMetadata($fileBasename, 'content_type')) {
        $contentType = getMetadata($fileBasename, 'content_type');
    } else {
        $contentType = mime_content_type($filePath);
    }

    $size = filesize($filePath);
    header('Content-Type: ' . $contentType);
    header('Accept-Ranges: bytes');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: X-Payment-Certificate');

    // Byte range requested?
    $range = getRange($size);
    if ($range !== false) {
        $start = $range[0];
        $end = $range[1];
        $length = $range[2];

        http_response_code(206);
        header('Content-Length: ' . $length);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);

        // Buffered passing through of file        
        $file = @fopen($filePath, 'rb');
        fseek($file, $start);
        $buffer = 1024 * 8;
        while(!feof($file) && ($pointer = ftell($file)) <= $end) {
            if ($pointer + $buffer > $end) {
                $buffer = $end - $pointer + 1;
            }
            set_time_limit(0);
            echo fread($file, $buffer);
            flush();
        }
        fclose($file);
        exit;
    }

    // Send whole file
    header('Content-Length: ' . $size);
    readfile($filePath);
    exit;
}

function getRange($size) {
    if (!isset($_SERVER['HTTP_RANGE'])) {
        return false;
    }

    list(, $rangeHeader) = explode('=', $_SERVER['HTTP_RANGE'], 2);
    if ($rangeHeader == '') {
        return false;
    }

    debug('range: ' . $rangeHeader);

    $range = explode('-', $rangeHeader, 2);

    // Case 1: range does not contain '-' or cantains '-' more than one time
    if (count($range) != 2) {
        false;
    }

    if ($range[0] != '' && $range[1] == '') {
        // Range has no end, e.g. '500-'
        $start = intval($range[0]);
        $end = $size - 1;
    } else if ($range[0] == '' && $range[1] != '') {
        // Range has no start, e.g. '-1000' (send last 1000 bytes)
        $start = $size - intval($range[1]);
        $end = $size - 1;
    } else if ($range[0] != '' && $range[1] != '') {
        // Range has start and end, e.g. '500-1000'
        $start = intval($range[0]);
        $end = intval($range[1]);
    } else {
        return false;
    }

    if ($start > $end) {
        return false;
    }

    if ($start >= $size || $end >= $size) {
        http_response_code(416);
        exit;
    }

    $length = $end - $start + 1;
    if ($length < 1 || $length > $size) {
        return false;
    }

    return [$start, $end, $length];
}

if (!isset($_GET['file'])) {
    notFound();
}

$filePath = findFile($_GET['file']);

if (!$filePath) {
    notFound();
}

if (!isset($_GET['paymentReceipt']) && !isset($_GET['paymentCert'])) {
    paymentRequired();
}

$secret = getSecret($_GET['file']);

if ($_GET['paymentReceipt']) {
    $receipt = new \SatoshiPay\Receipt($_GET['paymentReceipt'], $secret);
    if (!$receipt->isValid()) {
        paymentRequired();
    }
} else {
    if ($_GET['paymentCert'] != $secret) {
        paymentRequired();
    }
}

sendFile($_GET['file']);
