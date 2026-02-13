<?php
// --- CONFIGURATION ---
$uploadDir = "../images/";
$thumbsDir = "../thumbnails/";
$thumbWidth = 460;

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$maxSize = 16 * 1024 * 1024;

if (!is_dir($uploadDir))
    mkdir($uploadDir, 0755, true);
if (!is_dir($thumbsDir))
    mkdir($thumbsDir, 0755, true);

// --- Thumbnail with robust EXIF rotation ---
function createThumbnail($src, $dest, $thumbWidth)
{
    $info = getimagesize($src);
    if ($info === false)
        return false;

    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($src);
            break;
        case 'image/png':
            $image = imagecreatefrompng($src);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($src);
            break;
        default:
            return false;
    }

    // --- Robust iPhone EXIF rotation ---
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($src, 'IFD0');

        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $image = imagerotate($image, 180, 0);
                    break;
                case 6:
                    $image = imagerotate($image, -90, 0);
                    break;
                case 8:
                    $image = imagerotate($image, 90, 0);
                    break;
            }
        }
    }

    // --- Update dimensions after rotation ---
    $width = imagesx($image);
    $height = imagesy($image);

    // --- Create thumbnail ---
    $newWidth = $thumbWidth;
    $newHeight = intval($height * ($thumbWidth / $width));

    $thumb = imagecreatetruecolor($newWidth, $newHeight);

    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }

    imagecopyresampled(
        $thumb,
        $image,
        0,
        0,
        0,
        0,
        $newWidth,
        $newHeight,
        $width,
        $height
    );

    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($thumb, $dest, 85);
            break;
        case 'image/png':
            imagepng($thumb, $dest);
            break;
        case 'image/gif':
            imagegif($thumb, $dest);
            break;
    }

    return file_exists($dest);
}

// --- UPLOAD ---
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {

    $count = count($_FILES['images']['name']);
    $uploaded = 0;

    for ($i = 0; $i < $count; $i++) {

        $tmpPath = $_FILES['images']['tmp_name'][$i];
        $originalName = $_FILES['images']['name'][$i];
        $error = $_FILES['images']['error'][$i];
        $size = $_FILES['images']['size'][$i];
        $type = $_FILES['images']['type'][$i];

        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmpPath))
            continue;

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // HEIC → JPG
        if ($ext === "heic" || $ext === "heif") {
            if (class_exists("Imagick")) {
                try {
                    $img = new Imagick();
                    $img->readImage($tmpPath);
                    $img->setImageFormat("jpeg");

                    $safeName = uniqid("image_", true) . ".jpg";
                    $target = $uploadDir . $safeName;

                    if ($img->writeImage($target)) {
                        $img->clear();
                        $img->destroy();
                        if (createThumbnail($target, $thumbsDir . $safeName, $thumbWidth)) {
                            $uploaded++;
                        }
                    }
                } catch (Exception $e) {}
            }
            continue;
        }

        // Normal formats
        if ($size > $maxSize)
            continue;
        if (!in_array($type, $allowedTypes))
            continue;

        $safeName = uniqid("image_", true) . "." . $ext;
        $target = $uploadDir . $safeName;

        if (move_uploaded_file($tmpPath, $target)) {
            if (createThumbnail($target, $thumbsDir . $safeName, $thumbWidth)) {
                $uploaded++;
            }
        }
    }

    $message = $uploaded > 0
        ? "$uploaded image(s) were successfully uploaded!"
        : "No images could be uploaded.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload images</title>
    <link rel="stylesheet" href="../style.css">
</head>

<body>

    <div class="top-bar">
        <button class="home-btn" onclick="location.href='/'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#111" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;">
                <rect x="3" y="3" width="18" height="14" rx="2" ry="2"></rect>
                <circle cx="8" cy="10" r="2"></circle>
                <path d="M21 15l-5-5L5 21"></path>
            </svg>
            Back to gallery
        </button>
    </div>

    <div class="upload-container">
        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'successfully uploaded') !== false ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <h1>Upload images</h1>

        <form id="uploadForm" action="" method="POST" enctype="multipart/form-data">
            <input type="file" name="images[]" id="fileInput" accept="image/*" multiple hidden>

            <div class="upload-box" id="dropArea">
                <div style="font-size: 2.5rem; opacity: 0.6;">📁</div>
                <div style="margin-top: 10px;">
                    Drag and drop one or more images here<br>or click to select!
                </div>
            </div>

            <div id="preview"></div>

            <button type="submit" id="uploadBtn" disabled>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
					stroke="#111" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
					style="vertical-align: middle; margin-right: 6px;">
					<path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"></path>
					<polyline points="7 9 12 4 17 9"></polyline>
					<line x1="12" y1="4" x2="12" y2="16"></line>
				</svg>
                Upload images
            </button>
        </form>

    </div>

    <script>
        let dropUsed = false;

        const dropArea = document.getElementById("dropArea");
        const fileInput = document.getElementById("fileInput");
        const preview = document.getElementById("preview");
        const uploadBtn = document.getElementById("uploadBtn");

        function updateButtonState() {
            uploadBtn.disabled = fileInput.files.length === 0;
        }

        dropArea.addEventListener("click", () => fileInput.click());

        dropArea.addEventListener("dragover", (e) => {
            e.preventDefault();
            dropArea.classList.add("dragover");
        });

        dropArea.addEventListener("dragleave", () => {
            dropArea.classList.remove("dragover");
        });

        dropArea.addEventListener("drop", (e) => {
            e.preventDefault();
            dropArea.classList.remove("dragover");

            dropUsed = true;

            fileInput.files = e.dataTransfer.files;
            showPreviewMultiple(e.dataTransfer.files);
            updateButtonState();
        });

        fileInput.addEventListener("change", () => {
            if (dropUsed) {
                dropUsed = false;
                return;
            }

            showPreviewMultiple(fileInput.files);
            updateButtonState();
        });

        // --- Improved preview loader with reliable scroll ---
        function showPreviewMultiple(files) {
            preview.innerHTML = "";
            if (!files.length) {
                preview.style.display = "none";
                return;
            }
            preview.style.display = "flex";

            let loadedCount = 0;

            [...files].forEach(file => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = document.createElement("img");
                    img.onload = () => {
                        loadedCount++;
                        if (loadedCount === files.length) {
                            // All previews fully rendered → scroll now
                            window.scrollTo({
                                top: document.body.scrollHeight,
                                behavior: "smooth"
                            });
                        }
                    };
                    img.src = e.target.result;
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            });
        }
    </script>

</body>

</html>