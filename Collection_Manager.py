#!/usr/bin/python
'''

This tool needs in installation of 2 modules:

1) Module eyed3

The module eye3d is used to maintain tags in mp3 files in the collection folders.
Original files are not changed.

To install                  : sudo apt install eyed3
Find the path to the module : find /usr/lib -name eyed3
This gives something like   : /usr/lib/python3/dist-packages/eyed3

Update the path to the folder in the code below just after 'import sys' like:

sys.path.append('/usr/lib/python3/dist-packages')

Note that there is also a commandline tool with a capital D in it.

    eyeD3 mp3-file gives most important tags

2) Module soco

The module soco is used to start the inventory action on Sonos.

Documentation on the soco module can be found on https://github.com/SoCo/SoCo

Please install this way:

    Start a terminal:
    
    sudo -i
    cd 'to the folder where this script is' like :
    cd /var/www/html/Collection_Manager
    python3 -m venv venv    (<- this takes a while so wait...)
    source venv/bin/activate
    pip install soco
    deactivate

After that you can test by

    source venv/bin/activate
    python3 Collection_Manager.py start_sonos_scan
    deactivate
    
    or in 1 line like in Collection_Manager.php :
    
    ./venv/bin/python3 Collection_Manager.py start_sonos_scan

The response should be "OK Updating " and list your Sonos devices

'''

import sys

sys.path.append('/usr/lib/python3/dist-packages')

import base64
import io
import os
from pathlib import Path
from PIL import Image
import shutil

# check mp3 tag module installation

try:
    import eyed3
except:
    print(f"Error: please read installation instructions in {sys.argv[0]}")
    sys.exit(1)

def usage():
    print("")
    print("Usage:")
    print("")
    print(f"     python {sys.argv[0]} add_to_collection mp3-file collection_path")
    print(f"        - this copies the mp3-file and updates tags in the copy")
    print(f"     python {sys.argv[0]} rename_collection old_path new_path")
    print(f"        - this renames the folder and updates tags in the copies")
    print(f"     python {sys.argv[0]} get_artwork mp3-file")
    print(f"     python {sys.argv[0]} get_title mp3-file")
    print(f"     python {sys.argv[0]} start_sonos_scan")
    print("")
    sys.exit(1)

def resize_and_encode(image_bytes, target_size=(400, 400), mime='image/jpeg'):
    image = Image.open(io.BytesIO(image_bytes))
    image = image.convert("RGB")  # Zorg dat het veilig als JPEG opgeslagen kan worden
    image = image.resize(target_size, Image.LANCZOS)

    output = io.BytesIO()
    image.save(output, format='JPEG', quality=85)
    b64 = base64.b64encode(output.getvalue()).decode('utf-8')
    return f"data:{mime};base64,{b64}"
    
# arguments

if len(sys.argv) == 1:
    usage()

action = str(Path(sys.argv[1]))
    
if (action == "add_to_collection"):
    filein      = str(Path(sys.argv[2]))
    folderout   = str(Path(sys.argv[3]))

    # fileout

    shortfile = filein.split("/")[-1]
    fileout=folderout+"/"+shortfile

    # album

    collection = folderout.split("/")[-1]
    album_prefix = "("
    album_postfix = ")"
    album       = album_prefix + collection + album_postfix

    # copy audio file to collection folder

    try:
        shutil.copy(filein, fileout)
    except:
        print(f"Error: Can not copy '{filein}'")
        sys.exit(1)

    # find out if we need to convert to (new) mp3

    conversion_required = False
    
    filetype = filein.split(".")[-1]
    
    if (filetype != "mp3"):
        conversion_required = True
    else:
        try:
            audiofile = eyed3.load(fileout)
            if audiofile.tag is None:
                audiofile.initTag(version=(2, 3, 0))
            if audiofile.tag.version < (2, 3, 0):
                conversion_required = True
            audiofile.tag.save()
        except:
            print(f"Error: Can not add initial tags '{filein}'")
            sys.exit(1)

    # conversion if required
    
    if (conversion_required):
#        print("convert " + fileout + " to (new) mp3")
        import subprocess
        tempfile = fileout + ".mp3"
        

        subprocess.run([
    "ffmpeg",
    "-y",                # <-- overwrite without prompt
    "-i", fileout,
    "-vn",
    "-c:a", "libmp3lame",
    "-q:a", "2",
    tempfile
], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

        try:
            os.remove(fileout)
        except:
            print(f"Error: Can not delete file '{fileout}'")
            dummy=0
            
        fileout = tempfile       
        
    # update mp3 tags

    try:
        audiofile = eyed3.load(fileout)

    # we want to fix fields because different values will generate more albums

        artist = audiofile.tag.artist or "Unknown"
        album_artist = audiofile.tag.album_artist or "Unknown"
        title = audiofile.tag.title or shortfile
        old_album = audiofile.tag.album or "Unknown"
        
    # Save some data in the comment field

        newcomment = 'artist: ' + artist + '; album_artist: ' + album_artist + '; title: ' + title + '; album: ' + old_album

    # values for album_artist generate extra albums in Sonos so get rid of that

        if audiofile.tag.album_artist:
            audiofile.tag.album_artist = None

    # set/add required tags
    
        audiofile.tag.title = title + " (" + artist + ")"
        audiofile.tag.artist = "Collection"
        audiofile.tag.album = album
        audiofile.tag.comments.set(newcomment)
    
    # add artwork as Artist picture type 3

# Picture types
#0: Other
#1: Album artist or artist picture
#2: Media picture (like picture of album laying on a table)
#3: Front cover
#4: Back cover
#5: Extra illustrations
#6: Copy Rights picture
#7: Artist picture

# 0 Andere
# 1 32x32 pixels "file icon" (PNG-icon)
# 2 Andere icon
# 3 Cover (front) – meest gebruikt
# 4 Cover (back)
# 5 Leaflet page
# 6 Media (bijv. CD label)
# 7 Lead artist/performer
# 8 Artist/performer
# 9 Conductor
# 10 Band/Orchestra
# 11 Composer
# 12 Tekst schrijver (Lyricist)
# 13 Opgenomen locatie
# 14 Tijdens opname
# 15 Tijdens performance
# 16 Still uit film/video
# 17 Capture van een video screen
# 18 Bright coloured fish (grapje uit de standaard)
# 19 Illustratie
# 20 Logo van band/artist
# 21 Logo van uitgever/label

        # first find out if the cover is missing before we overwrite
        
        missing = True
        if audiofile.tag and audiofile.tag.images:
            for img in audiofile.tag.images:
                if img.picture_type == 3:  # Front cover : picture_type 3
                    missing = False
                    break

        if (missing):

            artwork_files = [
                "Folder.jpg", "folder.jpg", "AlbumArtSmall.jpg", "albumartsmall.jpg"
            ]

            img_data = None
            for afile in artwork_files:
                path = os.path.join(os.path.dirname(filein), afile)
                if os.path.exists(path):
                    with open(path, "rb") as img_file:
                        img_data = img_file.read()
                    break

            if img_data is None:
                script_dir = str(Path( __file__ ).parent.absolute())
                fallback_artwork   = script_dir + "/images/Folder.jpg"
                with open(fallback_artwork, "rb") as img_file:
                    img_data = img_file.read()

            audiofile.tag.images.set(3, img_data, "image/jpeg", u"Album artwork") # Front cover : picture_type 3
        
        audiofile.tag.save()
        print(f"OK added '{audiofile.tag.title}'")
    except:
        print(f"Error: Can not update '{fileout}'")
        try:
            os.remove(fileout)
        except:
            print(f"Error: Can not delete file '{fileout}'")
            dummy=0
        sys.exit()
elif (action == "get_title"):
    mp3_file      = str(Path(sys.argv[2]))

    try:
        audiofile = eyed3.load(mp3_file)

    #    print(audiofile.tag.title, ' * ' , audiofile.tag.album)
        print(audiofile.tag.title)

    except:
        print(mp3_file)

elif (action == "get_artwork"):
    mp3_file      = str(Path(sys.argv[2]))

    # try to get artwork from file
    
    audiofile = eyed3.load(mp3_file)
    if audiofile.tag and audiofile.tag.images:
        found = False
        for img in audiofile.tag.images:
            if img.picture_type == 3:  # Front cover : picture_type 3
                album_artwork = img
                found = True
                break
        if found:
            image_data = album_artwork.image_data
            result = resize_and_encode(image_data)
            if (result):
                print(result)
                sys.exit()     
    
    # try to get artwork from folder

    fallback_names = [
        'Welcome.png', 'Folder.jpg', 'folder.jpg', 'cover.jpg', 'Cover.jpg', 'album.jpg', 'Album.jpg'
    ]
            
    check_in_folder = os.path.dirname(mp3_file)
    for name in fallback_names:
        path = os.path.join(check_in_folder, name)
        if os.path.isfile(path):
            with open(path, 'rb') as f:
                image_data = f.read()
            result = resize_and_encode(image_data)
            if (result):
                print(result)
                sys.exit()     

    # try to get artwork from ./images
    
    check_in_folder = os.path.dirname(os.path.abspath(__file__)) + "/images"
    for name in fallback_names:
        path = os.path.join(check_in_folder, name)
        if os.path.isfile(path):
            with open(path, 'rb') as f:
                image_data = f.read()
            result = resize_and_encode(image_data)
            if (result):
                print(result)
                sys.exit()
                      
    sys.exit(1)        
    
elif (action == "rename_collection"):
    oldPath = str(Path(sys.argv[2]))
    newPath = str(Path(sys.argv[3]))

    try:
        shutil.move(oldPath, newPath)
        try:
            # update album in mp3;s
            folder = Path(newPath)                
            # album
            collection = newPath.split("/")[-1]
            album_prefix = "("
            album_postfix = ")"
            album       = album_prefix + collection + album_postfix
            
            # update album tab in all mp3s
            
            mp3s = list(folder.glob("*.mp3"))
            if (len(mp3s) != 0) :
              for mp3_file in mp3s:
                audiofile = eyed3.load(mp3_file)
                audiofile.tag.album = album
                audiofile.tag.save()

            print(f"OK renamed to '{newPath}'")
    
        except(e):
            print(f"Error: Can not update album in '{newPath}'")
            shutil.move(newPath, oldPath)
            sys.exit()
            
    except:
        print(f"Error: Can not rename folder '{oldPath}' to '{newPath}'")
        shutil.move(newPath, oldPath)
        sys.exit()

elif (action == "start_sonos_scan"):

# check soco installation

    try:
        import soco
    except:
        print(f"Error: please read installation instructions in {sys.argv[0]}")
        sys.exit(1)
    
    # Find all Sonos devices on jour LAN
    devices = soco.discover()

    if not devices:
        print("Error: No Sonos devices found.")
        sys.exit(1)

#    print(f"Found Sonos devices: {[device.player_name for device in devices]}")

    updating = False
    
    for device in devices:
#        print(f"Updating on {device.player_name} : { device.music_library.library_updating}")
        updating = updating or device.music_library.library_updating
    
    if not updating:
        
        for device in devices:
            result = device.music_library.start_library_update()
#            print(f"OK '{result}' '{[device.player_name for device in devices]}'  '{device.player_name}' ({device.ip_address}) ")

    updating_devices = ''
    for device in devices:
#        print(f"Updating on {device.player_name} : { device.music_library.library_updating}")
        if (device.music_library.library_updating):
            updating_devices = updating_devices + ' -> ' + device.player_name
        updating = updating or device.music_library.library_updating
        
    if updating:
        print("OK Updating on"+updating_devices)
    else:
        print("Error not updating")

else:
    print(action + " not supported")

