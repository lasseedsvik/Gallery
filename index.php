<?php
$thumbDir = "thumbnails/";
$imgDir = "images/";

// --- Scan images folder and group JPG + MOV ---
$files = array_filter(scandir($imgDir), function ($f) use ($imgDir) {
    return is_file($imgDir . $f);
});

$items = [];

// Group by base name
foreach ($files as $file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $base = pathinfo($file, PATHINFO_FILENAME);

    if (!isset($items[$base])) {
        $items[$base] = ['image' => null, 'video' => null];
    }

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $items[$base]['image'] = $file;
    }

    if ($ext === 'mov') {
        $items[$base]['video'] = $file;
    }
}

// Sort by newest (based on image file)
uasort($items, function ($a, $b) use ($imgDir) {
    $aFile = $a['image'] ?? $a['video'];
    $bFile = $b['image'] ?? $b['video'];
    return filemtime($imgDir . $bFile) - filemtime($imgDir . $aFile);
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <title>Freddy's cat gallery!</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="top-bar">
        <button class="upload-btn" onclick="location.href='upload/'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#111"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                style="vertical-align: middle; margin-right: 6px;">
                <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"></path>
                <polyline points="7 9 12 4 17 9"></polyline>
                <line x1="12" y1="4" x2="12" y2="16"></line>
            </svg>
            Upload pictures
        </button>
    </div>

    <h1>Freddy's cat gallery!</h1>

    <div class="gallery">
        <?php
        $index = 0;
        foreach ($items as $base => $data):
            if (!$data['image'])
                continue; // must have thumbnail image
        
            $thumb = $thumbDir . $data['image'];
            $fullImage = $imgDir . $data['image'];
            $fullVideo = $data['video'] ? $imgDir . $data['video'] : "";
            ?>
            <img src="<?= $thumb ?>" data-image="<?= $fullImage ?>" data-video="<?= $fullVideo ?>"
                data-index="<?= $index ?>" class="gallery-item" alt="">
            <?php
            $index++;
        endforeach;
        ?>
    </div>

    <div class="lightbox" id="lightbox">
        <span class="close-btn" id="closeBtn">×</span>
        <span class="arrow arrow-left" id="prevBtn">❮</span>
        <span class="arrow arrow-right" id="nextBtn">❯</span>

        <div class="lightbox-zoom-container" id="zoomContainer">
            <img id="lightbox-img" src="">
            <video id="lightbox-video" controls playsinline muted loop></video>
        </div>
    </div>

    <script>
        const items = [...document.querySelectorAll(".gallery-item")];
        const lightbox = document.getElementById("lightbox");
        const imgEl = document.getElementById("lightbox-img");
        const videoEl = document.getElementById("lightbox-video");
        const zoomContainer = document.getElementById("zoomContainer");
        const closeBtn = document.getElementById("closeBtn");
        const nextBtn = document.getElementById("nextBtn");
        const prevBtn = document.getElementById("prevBtn");

        let currentIndex = 0;

        // --- ZOOM STATE ---
        let scale = 1;
        let lastScale = 1;
        let startDistance = 0;
        let isPanning = false;
        let startX = 0, startY = 0;
        let currentX = 0, currentY = 0;

        function resetZoom() {
            scale = 1;
            lastScale = 1;
            currentX = 0;
            currentY = 0;
            applyTransform();
        }

        function applyTransform() {
            const el = videoEl.style.display === "block" ? videoEl : imgEl;
            el.style.transform = `translate(${currentX}px, ${currentY}px) scale(${scale})`;
        }

        // --- SHOW ITEM ---
        function showItem(index) {
            resetZoom();
            currentIndex = index;

            const item = items[index];
            const imgSrc = item.dataset.image;
            const videoSrc = item.dataset.video;

            if (videoSrc) {
                imgEl.style.display = "none";
                videoEl.style.display = "block";
                videoEl.src = videoSrc;
                videoEl.play();
            } else {
                videoEl.pause();
                videoEl.style.display = "none";
                imgEl.style.display = "block";
                imgEl.src = imgSrc;
            }

            lightbox.style.display = "flex";
            document.body.classList.add("no-scroll");
        }

        function closeLightbox() {
            lightbox.style.display = "none";
            videoEl.pause();
            document.body.classList.remove("no-scroll");
        }

        items.forEach((item, i) => {
            item.addEventListener("click", () => showItem(i));
        });

        function nextItem() {
            currentIndex = (currentIndex + 1) % items.length;
            showItem(currentIndex);
        }

        function prevItem() {
            currentIndex = (currentIndex - 1 + items.length) % items.length;
            showItem(currentIndex);
        }

        nextBtn.addEventListener("click", nextItem);
        prevBtn.addEventListener("click", prevItem);
        closeBtn.addEventListener("click", closeLightbox);

        lightbox.addEventListener("click", (e) => {
            if (e.target === lightbox) closeLightbox();
        });

        document.addEventListener("keydown", (e) => {
            if (lightbox.style.display !== "flex") return;

            if (e.key === "ArrowRight") nextItem();
            if (e.key === "ArrowLeft") prevItem();
            if (e.key === "Escape") closeLightbox();
        });

        // --- SWIPE ---
        let touchStartX = 0;
        let touchEndX = 0;

        lightbox.addEventListener("touchstart", (e) => {
            touchStartX = e.changedTouches[0].clientX;
        });

        lightbox.addEventListener("touchmove", (e) => {
            touchEndX = e.changedTouches[0].clientX;
        });

        lightbox.addEventListener("touchend", () => {
            const diff = touchStartX - touchEndX;
            if (Math.abs(diff) < 50 || scale > 1) return; // disable swipe while zoomed
            diff > 0 ? nextItem() : prevItem();
        });

        // --- PINCH TO ZOOM ---
        zoomContainer.addEventListener("touchstart", (e) => {
            if (e.touches.length === 2) {
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                startDistance = Math.hypot(dx, dy);
            } else if (e.touches.length === 1 && scale > 1) {
                isPanning = true;
                startX = e.touches[0].clientX - currentX;
                startY = e.touches[0].clientY - currentY;
            }
        });

        zoomContainer.addEventListener("touchmove", (e) => {
            if (e.touches.length === 2) {
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                const distance = Math.hypot(dx, dy);

                scale = Math.min(4, Math.max(1, lastScale * (distance / startDistance)));
                applyTransform();
                e.preventDefault();
            } else if (e.touches.length === 1 && isPanning) {
                currentX = e.touches[0].clientX - startX;
                currentY = e.touches[0].clientY - startY;
                applyTransform();
                e.preventDefault();
            }
        });

        zoomContainer.addEventListener("touchend", (e) => {
            if (e.touches.length === 0) {
                lastScale = scale;
                isPanning = false;
            }
        });

        // --- DOUBLE TAP TO ZOOM ---
        let lastTap = 0;
        zoomContainer.addEventListener("touchend", (e) => {
            const now = Date.now();
            if (now - lastTap < 250) {
                if (scale > 1) {
                    resetZoom();
                } else {
                    scale = 2;
                    lastScale = 2;
                    applyTransform();
                }
            }
            lastTap = now;
        });
    </script>

</body>

</html>