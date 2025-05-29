<?php

// ----- Includes

include "./Collection_Manager_Init.php";
include "./Authentication.php";

// The full path to the collections top folder

$collectionsDir = $baseDir . "/" .  $collections_top;

// We need to be able to create collections

if (!is_dir($collectionsDir)) {
    die("<h1 style='color: red;'>Error:</h1><p>Can not find initial collections folder<br><br>Please make sure I have at least read access to '$baseDir'<br><br>and write access to '$collectionsDir'</p>");
}

// Function to get pathnames and escape each [ and each ]  with a \

function safeGlob($pattern, $flags = 0) {
    $escaped = preg_replace_callback('/([\[\]])/', fn($m) => '\\' . $m[1], $pattern);
    return glob($escaped, $flags);
}

// Recursive function to create a list of all sub directories.
// ( the actual call excludes the top folder of our collections so we only get the 'source' albums )

function getMusicFolders($dir, $exclude) {
    $result = [];
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === $exclude) continue;
        $path = "$dir/$item";
        if (is_dir($path)) {
            $result[] = substr($path, strlen($GLOBALS['baseDir']) + 1);
            $result = array_merge($result, getMusicFolders($path, $exclude));
        }
    }
    return $result;
}

// Recursive delete, only used to delete collections

function deleteDirectory($dir) {
    if (!is_dir($dir)) return false;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $filePath = "$dir/$file";
        if (is_dir($filePath)) {
            deleteDirectory($filePath);
        } else {
            unlink($filePath);
        }
    }
    return rmdir($dir);
}

// ----- Start of GET processing

if (isset($_GET['start_sonos_scan']) ) {
    $venvpython3 = realpath(__DIR__ . "/venv/bin/python3");
    if ( is_file($venvpython3) ) {
        $escapedScript = escapeshellarg(__DIR__ . '/Collection_Manager.py');
        $cmd = "./venv/bin/python3 $escapedScript start_sonos_scan";
        $output = shell_exec($cmd);
        echo $output;
    } else {
        echo "Error: please read installation instructions";
    }
    exit;
}

// ----- Start of POST processing

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $playmedia = $_POST['playmedia'] ?? '';
    $src = $_POST['src'] ?? '';

//    $collection = $_POST['thisCollectionDir'] ?? '';
//    $dst = $collection;
    $thisCollectionDir = $_POST['thisCollectionDir'] ?? '';

    $filename = $_POST['filename'] ?? '';
    $collectionToDelete = $_POST['collectionToDelete'] ?? '';

    $srcPath = realpath("$baseDir/$src/$filename");
    $dstPath = realpath("$collectionsDir/$thisCollectionDir");

// Actions needed to play audio

    if ($action === 'get_title' && $filename && is_file($filename)) {
        $filename = escapeshellarg($filename);
        $escapedScript = escapeshellarg(__DIR__ . '/Collection_Manager.py');
        $cmd = "python3 $escapedScript $action $filename";
        $title = shell_exec($cmd);
        echo $title ;
        exit;
    }

    if ($action === 'get_artwork' && $filename && is_file($filename)) {
        $filename = escapeshellarg($filename);
        $escapedScript = escapeshellarg(__DIR__ . '/Collection_Manager.py');
        $cmd = "python3 $escapedScript $action $filename";
        $artwork = shell_exec($cmd);
        echo $artwork;
        exit;
    }

    if ($action === 'playmedia') {
        header('Content-Type: audio/mpeg');
        header('Content-Length: ' . filesize($filename));
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        readfile($filename);
        exit;
    }

// Actions to maintain collections

    if ($action === 'create_collection') {
        $newCollection = trim($_POST['text_input'] ?? '');
        if ($newCollection !== '') {
            $newPath = "$collectionsDir/" . "$newCollection";
            if (!is_dir($newPath)) {
                mkdir($newPath, 0755, true);
                if (!is_dir($newPath)) {
                    die("<h1 style='color: red;'>Error:</h1><p>Can not create collections<br><br>Please make sure I have write access to '$collectionsDir'</p>");
                }
                header("Location: ?collection=" . urlencode($newCollection));
                exit;
            }
        }
    }

    if ($action === 'delete_collection') {
        $collectionPath = "$collectionsDir/" . "$collectionToDelete";
        if (deleteDirectory($collectionPath)) {
            header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
            exit;
        }
    }

    if ($action === 'rename_collection') {
        $newCollection = trim($_POST['text_input'] ?? '');
        $oldCollection = trim($_POST['old_collection'] ?? '');
        $newPath = "$collectionsDir/" . "$newCollection";
        $oldPath = "$collectionsDir/" . "$oldCollection";
        $escapedScript = escapeshellarg(__DIR__ . '/Collection_Manager.py');
        $cmd = "python3 $escapedScript $action \"$oldPath\" \"$newPath\"";
        $output = shell_exec($cmd);
        if (strpos($output, 'OK') === 0) {
            header("Location: ?collection=" . urlencode($newCollection));
        } else {
            header("Location: ?collection=" . urlencode($oldCollection));
        }
    exit;
    }

    if ($action === 'add_to_collection') {
        $escapedSrc = escapeshellarg($srcPath);
        $escapedDst = escapeshellarg($dstPath);
        $escapedScript = escapeshellarg(__DIR__ . '/Collection_Manager.py');
        $cmd = "python3 $escapedScript $action $escapedSrc $escapedDst";
        $output = shell_exec($cmd);
        if (strpos($output, 'OK') !== 0) {
//            echo "Error Adding $filename to collection $thisCollectionDir.";
            echo "$output";
        } else {
            echo "OK Added $filename to collection $thisCollectionDir.";
        }
        exit;
    }

    if ($action === 'remove_from_collection') {
        $fileToDelete = realpath("$collectionsDir/$thisCollectionDir/$filename");
        if (is_file($fileToDelete) && strpos($fileToDelete, realpath($collectionsDir)) === 0) {
            unlink($fileToDelete);
            echo "OK Removed $filename from collection $thisCollectionDir.";
        } else {
            echo "Did not Remove $filename from collection $thisCollectionDir.";
        }
        exit;
    }

    if ($action === 'get_collection') {
        $currentCollection = trim($_POST['currentCollection'] ?? '');
        $result = [];
        $mp3Files = safeGlob("$collectionsDir/$currentCollection/*.mp3");
        $mp4Files = safeGlob("$collectionsDir/$currentCollection/*.mp4");
        $files = array_merge($mp3Files, $mp4Files);
        print_r(json_encode($files)) ;
        exit;
    }

}

// ----- Create HTML page


// Get all collections we have so we can build the select box to switch to another collection

$collections = array_filter(scandir($collectionsDir), fn($d) => is_dir("$collectionsDir/$d") && !in_array($d, ['.', '..']));

// Sort $collections array not case sensitive

usort($collections, function($a, $b) {
    return strcasecmp($a, $b); // case insensitive compare
});

// The selected collection in the GET, or when the html page loads we start with the first collection, if any

$currentCollection = $_GET['collection'] ?? ($collections[0] ?? '');

?>

<!DOCTYPE html>
<html style="overscroll-behavior: none; overflow-x:hidden"> <!-- disable pul down refresh on mobile -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=0.35,user-scalable=no">
    <link rel="icon" type="image/x-icon" href=<?php echo ("\"./images/favicon.ico?" . rand() . "\"") ; ?>>
    <title>Collection Manager</title>
    <style>
/* ----- style ----- */

/* Overlay to be shown in landscape orientation */

    #orientation-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: black;
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      pointer-events: auto;
    }

/* The color theme */

    body,  #stickyHeader  {
        background-color: #ebebeb;
    }
    
/* Disable selecting items / text on mobile web page */

    @media only screen and (max-width: 768px) { 
        body, html, * {
            user-select: none;  /* Disable text selection */
            -webkit-user-select: none; /* For Safari */
            -moz-user-select: none; /* For Firefox */
            -ms-user-select: none; /* For Internet Explorer/Edge */
        }
    }
    
/* Keep player etc visible on top while scrolling through albums */

    #stickyHeader {
        position: sticky;
        top: 0;
        z-index: 100;
        padding-bottom: 1px;
    }

/* Loader-overlay on whole page */

    #page-loader {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: white;
      z-index: 9999;
      display: flex;
      justify-content: center;
      align-items: center;
      pointer-events: all;
      opacity: 1;
    }

    /* Spinner-icon */
    
    #spinner {
      width: 200px;
      height: 200px;
      animation: spin 1s linear infinite; // rotation speed
    }

    /* Spinning animation */
    @keyframes spin {
      from { transform: rotate(0deg); }
      to   { transform: rotate(360deg); }
    }

    /* Fade-out effect */
    .fade-out {
      animation: fadeOut 3s ease-out forwards;
    }

    @keyframes fadeOut {
      from { opacity: 1; }
      to { opacity: 0; }
    }

/* Marquee to show track title */

    .marquee {
        width: 100%;
        line-height: 10px;
        background-color: lightgrey;
        color: Black;
        white-space: nowrap;
        overflow: hidden;
        box-sizing: border-box;
        font-size: 30px;
        border: 5px solid white;
        border-radius: 15px;
        box-shadow: 8px 8px 10px rgba(5, 5, 5, 0.5)
    }
    
    .marquee p {
        display: inline-block;
        padding-left: 100%;
        animation: marquee 20s linear infinite;
        animation-direction: alternate;
    }
    
    @keyframes marquee {
        0%   { transform: translate(-10%, 0); }
        100% { transform: translate(-90%, 0); }
    }

/* Artwork popup overlay */

    .popup-overlay {
      display: none;
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background-color: rgba(0, 0, 0, 0.6);
      justify-content: center;
      align-items: center;
      z-index: 1000;
    }

    /* Popup content */
    .popup-content img {
      width: 700px;
      height:700px;
      object-fit: contain;
      background: white;
      padding: 10px;
      border-radius: 10px;
    }

    .popup-overlay.active {
      display: flex;
    }
    
    /* button, select, input and image as button */
    
    button , select, input, .buttonImage {
        height: 50px;
        width: 50px;
        margin: 10px;
        border: 5px solid white; /* Rand rond de knop zelf */
        border-radius: 10px;
        box-shadow: 8px 8px 10px rgba(0, 0, 0, 0.7);
        background-color: rgba(0, 0, 0, 0.1);
    //    background-color: rgba(192, 192, 192, 1);
    //    background-color: lightgreen;
    //    display: flex;
        justify-content: center;
        align-items: center;
        font-size: 30px;
        font-weight : bold;
    }
    
    audio::-webkit-media-controls {
        display: none;
    }

    findMark {
      background-color: orange;
    }
    
    findMark.active {
      background-color: green;
    }
    
/* backlight */

    .backlight-container {
        width: 350px;       /* nice size is 400px */ 
        height: 350px;      /* nice size is 400px */ 
        overflow: hidden;
        position: relative;

      /* fading mask on all rims */
        -webkit-mask-image:
            linear-gradient(to top, transparent 0, black 30px, black calc(100% - 30px), transparent 100%), /* nice value is 50px for all 8 ..px values */
            linear-gradient(to left, transparent 0, black 30px, black calc(100% - 30px), transparent 100%);
        -webkit-mask-composite: intersect;
            mask-image:
            linear-gradient(to top, transparent 0, black 30px, black calc(100% - 30px), transparent 100%),
            linear-gradient(to left, transparent 0, black 30px, black calc(100% - 30px), transparent 100%);
        mask-composite: intersect;
        mask-size: cover;
        -webkit-mask-size: cover;
    }

    .backlight-disc {
        width: 600px;
        height: 600px;
        border-radius: 50%;
        background: conic-gradient(
            red,
            orange,
            yellow,
            lime,
            cyan,
            blue,
            magenta,
            red
        );
        animation: backlight-spin 10s linear infinite;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(0deg);
        transform-origin: center center;
        z-index: 0;
        animation-play-state: running;
    }

    @keyframes backlight-spin {
        from {
            transform: translate(-50%, -50%) rotate(0deg);
        }
        to {
            transform: translate(-50%, -50%) rotate(360deg);
        }
    }

    @keyframes backlight-spin-reverse {
        from {
            transform: translate(-50%, -50%) rotate(0deg);
        }
        to {
            transform: translate(-50%, -50%) rotate(-360deg);
        }
    }

    .centered-image {
        width: 300px;
        height: 300px;
        object-fit: cover;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1;
        border-radius: 10px;
    }

/* Use showMyAlert("text") as workaround for alert("text") giving checkbox with "Don't allow 'host' to prompt you again" */

    #myAlert {
        display: none;
        position: fixed;
        top: 40px;
        left: 50%;
        transform: translateX(-50%);
        background: #222;
        color: #fff;
        padding: 10px 20px;
        border-radius: 5px;
        z-index: 1000;
        font-family: sans-serif;
//        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        box-shadow: 8px 8px 10px rgba(0, 0, 0, 0.7);
    }
        
    </style>
</head>
<!-- ----- body -->
<body style="overscroll-behavior: none; margin-left: 5px; margin-right: 5px; ">
<div id="page-loader">
    <img src=<?php echo ("\"./images/spinner.png?" . rand() . "\"") ; ?> alt="*** * ***" id="spinner">
</div>


<div id="myAlert"></div>


<div id="stickyHeader">
    <hr>
    <center>

    <table style="width: 100%; table-layout: fixed; border-collapse: collapse;">
    <tr>

<!-- ----- r 1 c 1 Artwork -->

        <td height="300px;" style="border: 0px dotted; text-align: left;">

            <center>
              <div class="backlight-container">
                <div class="backlight-disc" style="display: none;" id="backlightEffect"></div>
                <!--img class="centered-image" src="./images/Folder.jpg" alt="Afbeelding"-->
                <img id="Artwork" class="centered-image" src=<?php echo ("\"./images/welcome.png?" . rand() . "\"") ; ?> alt="Click for popup" style="width:300px; cursor:pointer; border:5px solid white" onclick="openArtworkPopup(this.src)">
              </div>
            
            <br>
            
            <div class="marquee"><p id="songtitle" style="color: blue;"><font color="lightgrey">Dummy text with same color as body</font></p></div>
            
            </center>
            
        </td>

<!-- ----- r 1 c 2 Sonos; Collection Manager button, Logout and Collection Create, Rename, Delete and Find buttons -->

        <td style="text-align: center; vertical-align: middle;"> <!--  border: 1px dotted -->

            <img src="./images/sonos.png" onclick="startSonosScan()" class="buttonImage"></img> <!--  style='position: absolute; left: 10px;' -->
            <img src="./images/welcome.png" onclick="demo_loader(); toggleBackLightEffect()" class="buttonImage" ></img> <!-- style="border: 5px solid black;" -->
            <img src="./images/exit.png" onclick="logout()" class="buttonImage" src="./images/exit.png" style="float: right;"></img>

            <center>
            <h1 style="line-height: 0.2;">Select Collection</h1>
            <select class="buttonImage" style="width: 200px;" name="collection" onchange="get_collection(this.value); window.scrollTo({ top: 0, behavior: 'smooth' })">
                <?php foreach ($collections as $cl): ?>
                    <option value="<?php echo $cl; ?>"
                        <?php if (strcasecmp($cl, $currentCollection) === 0) echo 'selected'; ?>>
                        <?php echo $cl; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <form method="post">
        <!-- Delete button -->
                <button style="width:120px" id="DeleteButton" type="submit" <?php if ( ! in_array($thisuser, $adminusers_1)) { echo "disabled" ;} ?>
                    onclick="I am programmed by the javascript function get_collection which loads the selected collection ">Delete
                </button>
                <br>
        <!-- Create button -->
                <button style="width:120px"                  type="submit" <?php if ( ! in_array($thisuser, $adminusers_1)) { echo "disabled" ;} ?>
                    onclick="document.getElementById('action').value = 'create_collection';">Create</button>
        <!-- Rename button -->
                <button style="width:130px" id="RenameButton" type="submit" <?php if ( ! in_array($thisuser, $adminusers_1)) { echo "disabled" ;} ?>
                    onclick="I am programmed by the javascript function get_collection which loads the selected collection ">Rename
                </button>
                <br>
                <input type="text" name="text_input" id="text_input" pattern="[\-a-zA-Z0-9 ]+" title="Allowed characters: a-z A-Z 0-9 space and -" style="text-align: center;; width: 260px; margin-top: 0px; margin-bottom: 5px;"  placeholder="New Name / Find" >
        <!-- Hidden form fields -->
                <input type="hidden" id="action"             name="action"              value="to be filled by the Create Rename and Delete buttons">
                <input type="hidden" id="old_collection"     name="old_collection"      value="to be filled by the Rename button">
                <input type="hidden" id="collectionToDelete" name="collectionToDelete"  value="to be filled by the Delete button">
            </form>

        <!-- Find buttons -->

            <button onclick="findGoToPrevious()"><</button>
            <button style="width:130px" onclick="findSearchAndHighlight()">Find</button>
            <button onclick="findGoToNext()">></button>

            </center>
        </td>

    </tr>

<!-- ----- r 2 c 1 audio element and audio control buttons -->

    <tr>
        <td style="text-align: center; vertical-align: middle;">
            <!-- https://stackoverflow.com/questions/4126708/how-to-style-html5-audio-tag  -->
            <audio id="audio" controls style="width: 100%; height: 30px; border-radius: 10px;
                border: 5px solid white; box-shadow: 8px 8px 10px rgba(0, 0, 0, 0.7);"> <!--  background-color: #f0f0f0; -->
                your browser does not support the audio-element.
            </audio>

            <div>

                <img src="./images/prev.png" onclick="playListCounter = (playList.length + playListCounter - 2)  % playList.length ;
                        document.getElementById('audio').currentTime = document.getElementById('audio').duration;" class="buttonImage"></img>
                <img src="./images/pause.png" id="playpausebutton" onclick="audio_button(this.id)" class="buttonImage" ></img>
                <img src="./images/next.png" onclick="document.getElementById('audio').currentTime = document.getElementById('audio').duration;" class="buttonImage"></img>

                <img id="shufflebutton" onclick="audio_button(this.id)" class="buttonImage" src="./images/ordered.png"></img>
                <img src="./images/threestripes.png" id="loopbutton" onclick="audio_button(this.id)" class="buttonImage" title="Looping for Collection and Albums only"></img>

                <br>
                <img src="./images/down2.png" onclick="document.getElementById('audio').volume = Math.max(0, document.getElementById('audio').volume - 0.1);
                        document.getElementById('volume').innerHTML = Math.round(document.getElementById('audio').volume * 10);" class="buttonImage" ></img>
                <button id="volume" class="buttonImage" style="width:80px; height: 60px; position: relative; top: -27px; text-align: center; margin: auto;">3</button>
                <img src="./images/up2.png" onclick="document.getElementById('audio').volume = Math.min(10, document.getElementById('audio').volume + 0.1);
                        document.getElementById('volume').innerHTML = Math.round(document.getElementById('audio').volume * 10);" class="buttonImage"></img>
                <img src="./images/speakersound.png" id="soundmutebutton" onclick="audio_button(this.id)" class="buttonImage"></img>

            </div>

        </td>

<!-- ----- r 2 c 2 fast scroll buttons -->

        <td style="vertical-align: center;"> <!--   border: 1px dotted ;-->
            <center>
            <h1 style="line-height: 0.2;">Fast Scroll</h1>

            <img src="./images/beginning.png" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" class="buttonImage"></img>
            <img src="./images/middle.png" onclick="window.scrollTo({ top: document.body.scrollHeight / 2}); scrollToRelativeAlbum('next')" class="buttonImage"></img>
            <img src="./images/end.png" onclick="window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });" class="buttonImage"></img>
            <br>
            <img  src="./images/prev.png"onclick="scrollToRelativeAlbum('prev')" class="buttonImage"></img>

            <select class="buttonImage" style="width: 60px; height: 60px; position: relative; top: -27px" id="jumpSelect" onmouseup="jumpToAlbumByLetter(this.value)" onchange="jumpToAlbumByLetter(this.value)">
                <option value="">?</option>
                <?php
                // Numbers 0-9
                for ($i = 0; $i <= 9; $i++) {
                    echo "<option value=\"$i\">$i</option>\n";
                }

                // Letters A-Z
                foreach (range('A', 'Z') as $letter) {
                    echo "<option value=\"$letter\">$letter</option>\n";
                }
                ?>
            </select>

            <img src="./images/next.png" onclick="scrollToRelativeAlbum('next')" class="buttonImage"></img>

            </center>
        </td>

    </tr>
    </table>

<hr>
</center>
</div>

  <!-- ----- Landscape orientation Popup -->

    <div id="orientation-overlay">
        <img id="orientation-img" style="border:3px solid white; width:90vh" src="./images/welcome.png">
        <p id="orientation-p" style="color:white; font-size: 3rem;">&nbsp;&nbsp;&nbsp;Please turn screen to portrait.</p>
    </div>

  <!-- ----- Artwork Popup -->
  
    <div id="popup" class="popup-overlay" onclick="closeArtworkPopup()">
        <div class="popup-content">
            <img id="popup-img" src="" alt="Popup">
        </div>
    </div>

<!-- ----- selectedCollectionDetails -->

    <div id="selectedCollectionDetails">
    </div>
    
<!-- ----- album details -->

    <hr>
    <h1>Albums</h1>

    <?php // Get all music folders except the collections_top folder so we can show all albums and tracks

    $folders = getMusicFolders($baseDir, $collections_top);
    natcasesort($folders);
    $folders = array_values($folders);
    foreach ($folders as $folder):

        $mp3Files = safeGlob("$baseDir/$folder/*.mp3");
        $mp4Files = safeGlob("$baseDir/$folder/*.mp4");
        $mp3s = array_merge($mp3Files, $mp4Files);

        if ( $mp3s ) {
            echo ( "<h1 name=\"album " . $folder . "\">" .
            "<table><tr>" .
            "<td><img src=\"./images/ordered.png\"   onclick=\"playThisAlbum('all'     ,'" . addslashes($folder) . "')\" class=\"buttonImage\" ></img>" .
            "</td><td><img src=\"./images/shuffled.png\"  onclick=\"playThisAlbum('shuffle' ,'" . addslashes($folder) . "')\" class=\"buttonImage\" style=\"margin-right: 50px;\"></img></td><td>" .
            $folder . "</td></tr></table> </h1>");
        } else {
            echo ( "<h1><i>Albums " . $folder . " ...</i></h1>" );
        }
        ?>
        <table>
            <?php foreach ($mp3s as $mp3):
                $fname = basename($mp3); ?>
                <tr>
                    <?php if ($currentCollection): ?> <!-- do not show + button when we do not have any collection -->
                    <td>
                        <button <?php if (!in_array($thisuser, $adminusers_1)) { echo "disabled"; } ?>
                                onclick="add_to_collection('<?php echo htmlspecialchars(addslashes($folder)); ?>',
                                                           '<?php echo htmlspecialchars(addslashes($fname)); ?>', currentCollection)"
                            style="margin: 10px; width:50px;">
                            +
                        </button>
                        <input type="hidden" name="<?php echo htmlspecialchars($folder); ?>" value="<?php echo htmlspecialchars($mp3); ?>">
                    </td>
                    <?php endif; ?>

                    <td>
                        <form method="post" style="margin: 0;" class="playForm">
                            <input type="radio" style="height: 30px" name="play" data-filename="<?php echo htmlspecialchars($mp3, ENT_QUOTES); ?>"
                                   onclick="playbackController.cancel = true;
                                            toggleLooping('stopLooping');
                                            document.getElementById('audio').play();
                                            playstate = true;
                                            document.getElementById('playpausebutton').src = './images/play.png';
                                            submitPlayMedia(this.dataset.filename)">
                        </form>
                    </td>

                    <td>
                        <h1 style="line-height: 1.2; margin: 0;"><?php echo htmlspecialchars($fname); ?></h1>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>

<hr>
<script>

// ----- javascript functions

//currentCollection = ''

// ----- top r1 c1 artwork and popup functions
    function toggleBackLightEffect() {
      const effect = document.getElementById("backlightEffect");
      effect.style.display = (effect.style.display === "none") ? "block" : "none";
    }

    let directionNormal = true;

    function reverseSpin() {
        const disc = document.getElementById("backlightEffect");
        directionNormal = !directionNormal;
        if ( directionNormal) {
            disc.style.animation = "backlight-spin 10s linear infinite"
        } else{
            disc.style.animation = "backlight-spin-reverse 10s linear infinite";
        }
    }

    function toggleBackLightSpin(state) {
      const disc = document.getElementById("backlightEffect");
      console.log(state)
      if (state == 'start') {
            disc.style.animationPlayState = "running"
        } else{
            disc.style.animationPlayState = "paused";
        }
     }
    
    toggleBackLightSpin('stop')
    
    function toggleBackLightSpin_ok(state) {
      const disc = document.getElementById("backlightEffect");
      if (state == 'start') {
        if ( directionNormal) {
            disc.style.animation = "backlight-spin 10s linear infinite"
        } else{
            disc.style.animation = "backlight-spin-reverse 10s linear infinite";
        }
       } else {
        if ( directionNormal) {
            disc.style.animation = "backlight-spin 0s linear infinite"
        } else{
            disc.style.animation = "backlight-spin-reverse 0s linear infinite";
        }
      }
    }

    function openArtworkPopup(src) {
      document.getElementById('popup-img').src = src;
      document.getElementById('popup').classList.add('active');
    }

    function closeArtworkPopup() {
      document.getElementById('popup').classList.remove('active');
    }

// ----- top r1 c2 sonos logout and handle collection

    function startSonosScan() {

        if (confirm("Start Sonos Scan?") ) {
            if ( <?php if ( in_array($thisuser, $adminusers_2) ) { echo "true" ; } else { echo "false" ; } ; ?> ) {
                fetch('?start_sonos_scan=1')
                    .then(response => response.text())  // Get the response text
                    .then(data => {
                        showMyAlert('Sonos Scan Start: ' + data);  // Show the response text
                    })
                    .catch(error => {
                        showMyAlert('Error Start Sonos Scan: ' + error);
                    });

            } else {
                showMyAlert('Sonos Scan Start Not allowed for ' + '<?php echo $thisuser; ?>');
            }
        } else {
            showMyAlert('Sonos Scan Start Canceled');
        }
    }

    function logout() {
        fetch('?logout=1')
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url; // Go to new url
                }
            })
            .catch(error => {
                console.error('Error during logout:', error);
            });
    }

// ----- marquee and player functions

    playstate = false
    mutestate = false
    loopstate = false
    shufflestate = false
    function audio_button(id) {
//        console.log(id)
        if (id == "playpausebutton") {
            playstate = ! playstate;
            if (playstate) {
                document.getElementById('audio').play();
                document.getElementById('audio').muted = false
                document.getElementById(id).src = "./images/play.png"
                document.getElementById("soundmutebutton").src = "./images/speakersound.png"
                keepAlive();
            } else {
                document.getElementById('audio').pause()
                document.getElementById(id).src = "./images/pause.png"
            }
        }
        if (id == "soundmutebutton") {
            document.getElementById('audio').muted = !document.getElementById('audio').muted
            if (document.getElementById('audio').muted) {
                document.getElementById(id).src = "./images/speakermute.png"
            } else {
                document.getElementById(id).src = "./images/speakersound.png"
            }
        }
        if (id == "loopbutton") {
            toggleLooping()
        }
        if (id == "shufflebutton") {
//            shufflestate = ! shufflestate;
            if (shufflestate) {
                fillPlayList('all',  playingAlbum, true  )
            } else {
                fillPlayList('shuffle', playingAlbum, true  )
            }
        }
    }

    const marquee = document.querySelector('.marquee p');
    const audio = document.getElementById('audio');

    marquee.style.animationPlayState = 'paused';

    function stopMarquee() {
        marquee.style.animationPlayState = 'paused';
        toggleBackLightSpin('stop')
    }

    function startMarquee() {
        marquee.style.animationPlayState = 'running';
        toggleBackLightSpin('start')
    }

    audio.volume = 0.3;

    audio.addEventListener('pause', stopMarquee);

    audio.addEventListener('play', startMarquee);

    audio.addEventListener('ended', function() {
        if ( playingSingleTrack ) {
            this.currentTime = 0;
            this.play();
            keepAlive();
        }
        reverseSpin()
    }, false);

// ----- fast scroll functions

    function getAllAlbums() {
        return Array.from(document.querySelectorAll("[name*='album']"));
    }

    function getHeaderOffset() {
        const header = document.getElementById("stickyHeader");
        return header ? header.offsetHeight : 0;
    }

    function scrollToRelativeAlbum(direction) {
        const albums = getAllAlbums();
        const scrollY = window.scrollY;
        const offset = getHeaderOffset();

        if (direction === 'next') {
            for (const el of albums) {
                if (el.offsetTop > scrollY + offset + 1) {
                    window.scrollTo({ top: el.offsetTop - offset, behavior: 'smooth' });
                    break;
                }
            }
        } else if (direction === 'prev') {
            for (let i = albums.length - 1; i >= 0; i--) {
                if (albums[i].offsetTop < scrollY - 1) {
                    window.scrollTo({ top: albums[i].offsetTop - offset, behavior: 'smooth' });
                    break;
                }
            }
        }
    }

    function jumpToAlbumByLetter(letter) {
        if (!letter) return;

        const albums = document.querySelectorAll("[name^='album ']"); // space after album because it is followed by the name of the album
        const offset = getHeaderOffset();

        for (const el of albums) {
            const name = el.getAttribute('name');
            if (name && name.length > 6) {
                const firstChar = name.charAt(6).toUpperCase();
                if (firstChar === letter) {
                    window.scrollTo({
                        top: el.offsetTop - offset,
                        behavior: 'smooth'
                    });
                    break;
                }
            }
        }
    }

// ----- play functions

    loopPlaylist = false
    playingSingleTrack = true

    function toggleLooping(select="stopLooping or startLooping") {

        if (select ==  'stopLooping')       { loopPlaylist = false; playingSingleTrack = true } // called during activation of single track
        else if (select ==  'startLooping') { loopPlaylist = true; playingSingleTrack = false}  // called during album / collection start
        else if ( ! playingSingleTrack )       { loopPlaylist = ! loopPlaylist; }               // called by toggle/loop button

        if (loopPlaylist) {
            document.getElementById("loopbutton").src = "./images/loop.png"
        } else {
            document.getElementById("loopbutton").src = "./images/threestripes.png"
        }
    }

// when we are playing music we want to avoid timeouts

    let keepAliveInterval = null;

    function keepAlive() {
    // This is called by submitPlayMedia which starts the actual track
        if (keepAliveInterval) return; // already active

        keepAliveInterval = setInterval(() => {
            const audio = document.querySelector('audio');

            if (audio && !audio.paused && !audio.ended ) { // && audio.currentTime > 0) {
                // Send keepalive
                fetch('?keepalive=1');
            } else {
                // Stop keepalive
                clearInterval(keepAliveInterval);
                keepAliveInterval = null;
            }
        }, <?php echo ( $timeout_duration * 1000 / 3 ) ; ?> ); // 3 times during timeout period
    }

// Start an actual track

    let currentAudioURL = null;

    function submitPlayMedia(filePath) {
        document.getElementById("songtitle").innerHTML = "...........";

        // Uncheck all other tracks radio buttons
        document.querySelectorAll('input[type="radio"][name="play"]').forEach(function(radio) {
            radio.checked = false;
        });

        // Check only the active track radio button
        let clicked = document.querySelector('input[type="radio"][name="play"][data-filename="' + filePath + '"]');
        if (clicked) clicked.checked = true;

        const formData = new FormData();
        formData.append('action', 'playmedia');
        formData.append('filename', filePath);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error("Playback failed");
            return response.blob();
        })
        .then(blob => {
            const audio = document.querySelector('audio');

            // Revoke previous URL if it exists
            if (currentAudioURL) {
                URL.revokeObjectURL(currentAudioURL);
                currentAudioURL = null;
            }

            // Create and use new URL
            const url = URL.createObjectURL(blob);
            currentAudioURL = url;
            audio.src = url;
            audio.play()
                .then(() => {
                    keepAlive(); // start keepAlive when not already active
                })
                .catch(err => console.warn('Audio could not be played:', err));

            // Revoke the URL once the audio is done playing
    //        audio.onended = () => {
    //            URL.revokeObjectURL(currentAudioURL);
    //            currentAudioURL = null;
    //        };

            return fetch('', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'get_title',
                    filename: filePath
                })
            });
        })
        .then(response => response.text())
        .then(title => {
            document.getElementById("songtitle").innerHTML = title;

            return fetch('', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'get_artwork',
                    filename: filePath
                })
            });
        })
        .then(response => response.text())
        .then(base64image => {
            document.getElementById("Artwork").src = base64image;
            document.getElementById('popup-img').src = base64image;
            document.getElementById("orientation-img").src = base64image;
            document.getElementById("orientation-p").innerHTML = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" + document.getElementById("songtitle").innerHTML;

        })
        .catch(err => alert("Error during playing, getting title or getting artwork: " + err));
    }

    function shuffle(array) {
        let currentIndex = array.length;

        // While there remain elements to shuffle...
        while (currentIndex != 0) {

        // Pick a remaining element...
            let randomIndex = Math.floor(Math.random() * currentIndex);
            currentIndex--;

            // And swap it with the current element.
            [array[currentIndex], array[randomIndex]] = [
            array[randomIndex], array[currentIndex]];
        }
    }

    let playbackController = { cancel: false };

    playList = []
    playListCounter = 0
    playingFile = ''
    playingAlbum = ''

    function fillPlayList(mode, album, alreadyRunning ) {
        if ( alreadyRunning && (album == '') ) {
//            console.log("Unshuffle or (Re-)Shuffle button clicked before any album ever ran")
        } else {
            playingAlbum = album;
            playList = [];
        // Find all input elements in the HTML with name='this album'
            const inputs = document.querySelectorAll('input[name="' + album + '"]'); // name= equal ; name*= contains
        // Add them all to the playList
            inputs.forEach(input => {
                playList.push(input.value);
            });

            if (playList.length > 0) {

                shufflestate = (mode === "shuffle")
                if (shufflestate) {
                    shuffle(playList);
                    document.getElementById("shufflebutton").src = "./images/shuffled.png"
                } else {
                    document.getElementById("shufflebutton").src = "./images/ordered.png"
                }
//                console.log(shufflestate)

                if (alreadyRunning) {
                    playListCounter = 0
                    while (playList[playListCounter] != playingFile ) { playListCounter = playListCounter + 1 }
                }
            }
        }
    }

    async function playThisAlbum(mode, album) {

//        console.log(album)
        playstate = true; document.getElementById('playpausebutton').src = './images/play.png';

        toggleLooping('startLooping');
//        alert(mode + "  " + album)
        // Cancel previous loop
        playbackController.cancel = true;

        // Wait a moment to allow possible running `playThisAlbum` to stop
        await new Promise(r => setTimeout(r, 500));

        // Create new controller for this new running `playThisAlbum`
        const myController = { cancel: false };
        playbackController = myController;

        fillPlayList(mode, album, false)
        const audio = document.querySelector('audio');

        playListCounter = 0
        while (playList.length > 0 && (loopPlaylist || playListCounter < playList.length ) ) {
            if (playListCounter == playList.length ) { playListCounter = 0 }
            // Check if me running is still valid
            if (myController.cancel) {
//                console.log("Playlist stopped by new one.");
                return;
            }

//            console.log("play : "+playList[playListCounter]);
            playingFile = playList[playListCounter];
            await playSingle(audio, playList[playListCounter]);
//            console.log("played : "+playList[playListCounter]);

            // Check again after wait
            if (myController.cancel) {
//                console.log("Playlist stopped during playing.");
                return;
            }
            playListCounter = playListCounter + 1
        }
    }

    // Function to play a single track and wait until it is finished
    function playSingle(audioElement, filename) {
        return new Promise((resolve) => {
            // Create event listener which continues to the next track after this one finishes
            audioElement.onended = () => {
                resolve();
            };

            // Start media file
            submitPlayMedia(filename);
        });
    }

// ----- collection functions

    // add a \ before each of the characters between / and /
    function addslashes(str) {
        return str.replace(/([\\'"])/g, '\\$1');
    }
    // replace some special characters by html code like in php
    function htmlspecialchars(str) {
        if (typeof str !== "string") return str;
        return str
            .replace(/&/g, '&amp;')   // Needs to be first ;-)
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;'); // Optional, like ENT_QUOTES in PHP
    }

    function get_collection(collection_to_get) {
        currentCollection = collection_to_get
        const formData = new FormData();
        formData.append('action', 'get_collection');
        formData.append('currentCollection', currentCollection);

        fetch('', { // '' is this php
            method: 'POST',
            body: formData
        })
        .then(response => response.json())  // Receive data
        .then(data => {
            selectedCollectionDetails = document.getElementById("selectedCollectionDetails")
            let innerHTML="";
            innerHTML+="<h1 name=\"album\">"
            innerHTML+="<table>"
            innerHTML+="<tr><td><img src=\"./images/ordered.png\"  onclick=\"playThisAlbum('all' , '" + currentCollection + "')\" class=\"buttonImage\" ></img>"
            innerHTML+="</td><td><img src=\"./images/shuffled.png\"  onclick=\"playThisAlbum('shuffle' , '" + currentCollection + "')\" class=\"buttonImage\" style=\"margin-right: 50px;\"></img>"
            innerHTML+="</td><td>Collection '" + currentCollection + "'</td></tr></table></h1>"
            innerHTML+="<table>"
            data.forEach(item => {
                const fileName = item.split(/(\\|\/)/g).pop();  // Remove path so get file name
                innerHTML+="<tr><td>"
                    innerHTML+="<button  style=\"margin: 5px;\" "
                    // disable the '-' button for non admins
                    <?php if ( ! in_array($thisuser, $adminusers_1)) { echo ("innerHTML+=\"disabled\"") ; } ?>

                    innerHTML+=" onclick=\"remove_from_collection('" + htmlspecialchars(addslashes(fileName)) + "','" + currentCollection + "');\">-</button>"
                    innerHTML+="<input type=\"hidden\" name=\"" + currentCollection + "\" value=\"" + item + "\">"

                innerHTML+="</td><td>"

                    innerHTML+="<form method=\"post\" style=\"margin: 0;\" class=\"playForm\">"
                        innerHTML+="<input type=\"radio\" style=\"height: 30px\" name=\"play\" data-filename=\"" + item + "\""
                        innerHTML+="onclick=\"   playbackController.cancel = true;"
                            innerHTML+="toggleLooping('stopLooping');"
                            innerHTML+="playstate = true; document.getElementById('playpausebutton').src = './images/play.png';"
                            innerHTML+="submitPlayMedia(this.dataset.filename)\">"
                    innerHTML+="</form>"

                innerHTML+="</td><td>"

                    innerHTML+="<h1 style=\"line-height: 1.2; margin: 0;\">" + htmlspecialchars(fileName) + "</h1>"

                innerHTML+="</td></tr>"
            });
            innerHTML+="</table>"
            selectedCollectionDetails.innerHTML=innerHTML
            // reprogram the delete collection button
            const DeleteButton = document.getElementById("DeleteButton")
            DeleteButton.onclick=function(){
                document.getElementById('action').value = 'delete_collection' ;
                document.getElementById('collectionToDelete').value = currentCollection;
                return confirm("Delete collection '" + currentCollection + "' ?")
                };
            // reprogram the rename collection button
            const RenameButton = document.getElementById("RenameButton")
            RenameButton.onclick=function(){
                newname = document.getElementById('text_input').value;
                document.getElementById('action').value = 'rename_collection' ;
                document.getElementById('old_collection').value = currentCollection;
                return confirm("Rename collection '" + currentCollection + "' to '" + newname + "' ?" )
                };
        })
        .catch(error => {
            console.error('Error during get_collection:', error);
        });
    }

    function add_to_collection(src, fileName, currentCollection) {

        if (confirm("Add '" + fileName + "' to '" + currentCollection + "' ?") ) {

            if ( ! fileName.endsWith(".mp3") ) { alert("Migrating to mp3 takes a while....") }
            const formData = new FormData();
            formData.append('action', 'add_to_collection');
            formData.append('filename', fileName);
            formData.append('thisCollectionDir', currentCollection);
            formData.append('src', src);

            fetch('', { // '' is this php
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                showMyAlert(data);  // Show the response text
    //            console.log(data);
            })
            .catch(error => {
                console.error('Error during add_to_collection:', error);
            });

            get_collection(currentCollection)
        }
    }

    function remove_from_collection(fileName, currentCollection) {

        if (confirm("Remove '" + fileName + "' from '" + currentCollection + "' ?") ) {

            const formData = new FormData();
            formData.append('action', 'remove_from_collection');
            formData.append('filename', fileName);
            formData.append('thisCollectionDir', currentCollection);


            fetch('', { // '' is this php
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
    //            console.log(data);
            })
            .catch(error => {
                console.error('Error during remove_from_collection:', error);
            });

            get_collection(currentCollection)
        }
    }
// ----- functions Find : find, find next , find previous

    let findMatches = [];
    let findCurrentIndex = -1;

    function findClearHighlights() {
        document.querySelectorAll("findMark").forEach(findMark => {
            const parent = findMark.parentNode;
            parent.replaceChild(document.createTextNode(findMark.textContent), findMark);
            parent.normalize();
        });
        findMatches = [];
        findCurrentIndex = -1;
    }

    function findSearchAndHighlight() {
        findClearHighlights();
        const term = document.getElementById("text_input").value;
        if (!term) return;

        const lowerTerm = term.toLowerCase();
        const textNodes = [];

        const walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode(node) {
                    return (
                        node.parentNode.offsetParent !== null &&
                        node.textContent.toLowerCase().includes(lowerTerm)
                    ) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
                }
            }
        );

        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }

        textNodes.forEach(node => {
            const parent = node.parentNode;
            const text = node.textContent;
            const lowerText = text.toLowerCase();
            const fragment = document.createDocumentFragment();
            let i = 0;

            while (i < text.length) {
                const index = lowerText.indexOf(lowerTerm, i);
                if (index === -1) {
                    fragment.appendChild(document.createTextNode(text.substring(i)));
                    break;
                }

                if (index > i) {
                    fragment.appendChild(document.createTextNode(text.substring(i, index)));
                }

                const findMark = document.createElement("findMark");
                findMark.textContent = text.substr(index, term.length);
                fragment.appendChild(findMark);
                findMatches.push(findMark);

                i = index + term.length;
            }

            parent.replaceChild(fragment, node);
        });

        if (findMatches.length > 0) {
            findCurrentIndex = 0;
            findScrollToMatch(findCurrentIndex);
        }
    }

    function findScrollToMatch(index) {
        findMatches.forEach(m => m.classList.remove('active'));
        const el = findMatches[index];
        el.classList.add('active');
        const top = el.getBoundingClientRect().top + window.scrollY;
        window.scrollTo({ top: top - getHeaderOffset(), behavior: 'smooth' });
    }

    function findGoToNext() {
        if (findMatches.length === 0) return;
        findCurrentIndex = (findCurrentIndex + 1) % findMatches.length;
        findScrollToMatch(findCurrentIndex);
    }

    function findGoToPrevious() {
        if (findMatches.length === 0) return;
        findCurrentIndex = (findCurrentIndex - 1 + findMatches.length) % findMatches.length;
        findScrollToMatch(findCurrentIndex);
    }

// ----- functions On Load

    window.addEventListener('DOMContentLoaded', () => {

        const select = document.querySelector('select[name="collection"]');

        if (select && select.options.length == 0) {
// Hide div selectedCollectionDetails when we do not have any collection yet
            const div = document.getElementById('selectedCollectionDetails');
            div.style.display = 'none';
        } else {
// Get details first collection
            get_collection("<?php echo $currentCollection; ?>")
        }

        const loader = document.getElementById('page-loader');
        loader.classList.add('fade-out');
        setTimeout(() => loader.style.display = 'none', 3000); // 3 seconds for fade-out duration
    //    setTimeout(() => loader.style.display = 'flex', 15000); // show second time.
    });

    function demo_loader() {

        // Start spinning forever

        const loader = document.getElementById('page-loader');
        loader.classList.remove('fade-out');
        loader.style.opacity = '1';
        loader.style.display = 'flex';

        // fade out spinner to avoid forever spinning

        setTimeout(() => {
            loader.classList.add('fade-out');
            setTimeout(() => loader.style.display = 'none', 5000); // 5 seconds for fade-out duration
        }, 2000); // start fade-out after 2 seconds
    }

// ----- Landscape rotation on mobile device

    const userAgent = navigator.userAgent || navigator.vendor || window.opera;
    const isMobileDevice = /Mobi|Android|iPhone|iPad|iPod|Windows Phone/i.test(userAgent) ||
    userAgent.includes("Mozilla/5.0 (X11; Linux x86_64; rv:138.0) Gecko/20100101 Firefox/138.0"); // for my tablet which uses this userAgent

    function checkOrientation() {
        const isPortrait = window.matchMedia("(orientation: portrait)").matches;
        const overlay = document.getElementById("orientation-overlay");

        if (!isMobileDevice) {
        // Never show overlay on not mobile device
            overlay.style.display = "none";
            document.body.style.overflow = "";
            return;
        }

        // Only on mobile we check the orientation
        if (isPortrait) {
            overlay.style.display = "none";
            document.body.style.overflow = "";
        } else {
            overlay.style.display = "flex";
            document.body.style.overflow = "hidden";
        }
    }

    // Initiate and on orientation change and resize
    checkOrientation();
    window.addEventListener("orientationchange", checkOrientation);
    window.addEventListener("resize", checkOrientation);

    if ( false) { // debug routine adds eventlisteners for audio events ( false :: is disabled  )
        const audioo = document.getElementById('audio');
        [
          'play', 'pause', 'ended', 'playing', 'waiting', 'canplay',
          'canplaythrough', 'stalled', 'loadstart', 'loadedmetadata',
          'loadeddata', 'progress', 'durationchange', 'timeupdate',
          'volumechange', 'ratechange', 'seeked', 'seeking', 'error'
        ].forEach(eventName => {
          audioo.addEventListener(eventName, () => {
            console.log(`Event: ${eventName}`);
          });
        });
    }

    function showMyAlert(message) {
        const alertBox = document.getElementById("myAlert");
        alertBox.textContent = message;
        alertBox.style.display = "block";
        setTimeout(() => {
            alertBox.style.display = "none";
        }, 5000); // Disappear after na 5 seconds
    }

    
</script>
<!-- ----- /body -->
</body>
</html>
