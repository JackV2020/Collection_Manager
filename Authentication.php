<?php

// ----- Include to configure Authentication.php

include "./Authentication_Init.php";

/*

Available in the php which includes this Authentication.php:

    - CML function to write logging to ./Collection_Manager.log
            >>> when this is enabled in Authentication_Init.php <<<
    - $thisuser
            the name of the current user
    - $adminusers_1
            to check in_array($thisuser, $adminusers_1)
    - $adminusers_2
            to check in_array($thisuser, $adminusers_2)
            you may give these extra rights
                ( Collection Manager uses this to allow Sonos Scan )
    - $timeout_duration
            >>> See $timeout_duration exceptions after line 84 below <<<
            can be used in a keepalive routine like :
<script>

// Collection Manager uses this when music is started in the audio element named 'audio'

let keepAliveInterval = null;

function keepAlive() {
    if (keepAliveInterval) return; // already active

    keepAliveInterval = setInterval(() => {
        const audio = document.querySelector('audio');

        if (audio && !audio.paused && !audio.ended && audio.currentTime > 0) {
            // Something is playing
            fetch('?keepalive=1');
//            console.log("keepalive sent");
        } else {
            // No audio active → stop
            clearInterval(keepAliveInterval);
            keepAliveInterval = null;
//            console.log("keepalive stopped");
        }
    }, <?php echo ( $timeout_duration * 1000 / 3 ) ; ?> ); // each third of timeout_duration
}

</script>
*/

// ----- A log file function Collecttion Manager Log

function CML($line) {
// Log message in log file
// List of valid timezones: http://www.php.net/manual/en/timezones.php
    if ($Authentication_Logging) {
        date_default_timezone_set("Europe/Amsterdam");
        $Collection_Manager_Logfile = __DIR__ . "/Collection_Manager.log";
        $to_log  = date("Y-m-d H:i:s") . " - " . $line . "\n";
        file_put_contents($Collection_Manager_Logfile, $to_log , FILE_APPEND);
    }
}

// Basic Authentication

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($valid_passwords[$_SERVER['PHP_AUTH_USER']]) ||
    $valid_passwords[$_SERVER['PHP_AUTH_USER']] != $_SERVER['PHP_AUTH_PW']) {
    if ( ! in_array($_SERVER['REMOTE_ADDR'] , $my_ips ) ) {
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            CML( "IP " . $_SERVER['REMOTE_ADDR'] . " Login Failure: " . $_SERVER['PHP_AUTH_USER'] . " " . $_SERVER['PHP_AUTH_PW']);
        } else {
            CML( "IP " . $_SERVER['REMOTE_ADDR'] . " Login Request" );
        }
    }
    header('WWW-Authenticate: Basic realm="Protected Area"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authorization required';
    exit;
}

session_start();

$thisuser = $_SERVER['PHP_AUTH_USER'];

// $timeout_duration exceptions

//I use these during testing
//if ($thisuser == "z" )  { $timeout_duration = 24 * 60 * 60; } // 24 hours
//if (in_array($thisuser, $adminusers_1)) { $timeout_duration = 24 * 60 * 60; }  // 24 hours

// First action after login ?

if (!isset($_SESSION['LAST_ACTIVITY'])) {
    if ( ! in_array($_SERVER['REMOTE_ADDR'] , $my_ips ) ) {
        CML("IP " . $_SERVER['REMOTE_ADDR'] . " Login Success: " . $thisuser);
    }
}

// Session expired due to inactivity ?

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    if ( ! in_array($_SERVER['REMOTE_ADDR'] , $my_ips ) ) {
        CML("IP " . $_SERVER['REMOTE_ADDR'] . " Timeout: " . $thisuser);
    }
    // Force new login
    // Redirect to web server home page

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $servername = $_SERVER['SERVER_NAME'];
    $port = $_SERVER['SERVER_PORT'];

    $url = $protocol . '://' . $servername . ':' . $port  ; // . '/Collection_Manager/Collection_Manager.php';

    session_unset();
    session_destroy();
    header('WWW-Authenticate: Basic realm="Protected Area"');
    header('HTTP/1.0 401 Unauthorized');
?> <html>
<html>
<head>
<meta http-equiv="refresh" content="3;url=<?php echo $url; ?>">
</head>
<body>
<center> <h1>Session Expired</h1> </center>
</body>
</html>
<?php
    exit;
}

// Keepalive while playing music

if (isset($_GET['keepalive'])) {
//    CML("IP " . $_SERVER['REMOTE_ADDR'] . " Keepalive User: " . $thisuser);
    $_SESSION['LAST_ACTIVITY'] = time();
    exit;
}

// Logout

if (isset($_GET['logout']) ) {
    if ( ! in_array($_SERVER['REMOTE_ADDR'] , $my_ips ) ) {
        CML("IP " . $_SERVER['REMOTE_ADDR'] . " Logout User: " . $thisuser);
    }
    // Force session expiration

    $_SESSION['LAST_ACTIVITY'] = $_SESSION['LAST_ACTIVITY'] - $timeout_duration ;

    // Redirect to web server home page

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $servername = $_SERVER['SERVER_NAME'];
    $port = $_SERVER['SERVER_PORT'];

    $url = $protocol . '://' . $servername . ':' . $port  ; // . '/Collection_Manager/Collection_Manager.php';

    header("Location: $url");

    exit;
}

$_SESSION['LAST_ACTIVITY'] = time();

?>
