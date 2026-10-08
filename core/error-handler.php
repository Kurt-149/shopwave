<?php
$isLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || 
               strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;

function shopwaveErrorHandler($errno, $errstr, $errfile, $errline) {
    global $isLocalhost;
    
    error_log("SHOPWAVE ERROR [$errno] $errstr in $errfile line $errline");
    
    if ($isLocalhost) {
        echo "<div style='background:#fee;padding:10px;margin:10px;border:1px solid #faa;'>";
        echo "<strong>Error:</strong> $errstr<br>";
        echo "<small>in $errfile line $errline</small>";
        echo "</div>";
    }
    return true;
}

set_error_handler('shopwaveErrorHandler');

function shopwaveExceptionHandler($exception) {
    global $isLocalhost;
    
    error_log("SHOPWAVE EXCEPTION: " . $exception->getMessage() . " in " . 
               $exception->getFile() . " line " . $exception->getLine());
    
    http_response_code(500);
    
    if ($isLocalhost) {
        echo "<h1>Exception</h1>";
        echo "<p>" . $exception->getMessage() . "</p>";
        echo "<p>in " . $exception->getFile() . " line " . $exception->getLine() . "</p>";
    } else {
        echo "<h1>Something went wrong</h1>";
        echo "<p>Please try again later.</p>";
    }
    exit;
}

set_exception_handler('shopwaveExceptionHandler');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("SHOPWAVE FATAL: " . $error['message'] . " in " . $error['file'] . " line " . $error['line']);
    }
});