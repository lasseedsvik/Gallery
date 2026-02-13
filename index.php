<?php
$thumbDir = "thumbnails/";
$imgDir = "images/";

// Get files
$thumbs = array_values(array_filter(scandir($thumbDir), function ($file) use ($thumbDir) {
    return is_file($thumbDir . $file);
}));

// Sort by newest files
usort($thumbs, function ($a, $b) use ($thumbDir) {
    return filemtime($thumbDir . $b) - filemtime($thumbDir . $a);
});
?>
<!DOCTYPE html>
<html lang="sv">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <title>Freddy's cat gallery!</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300..700;1,300..700&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
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

    <h1>
        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;">
            <rect x="3" y="3" width="18" height="14" rx="2" ry="2"></rect>
            <circle cx="8" cy="10" r="2"></circle>
            <path d="M21 15l-5-5L5 21"></path>
        </svg>
        Freddy's cat gallery!
    </h1>

    <div class="gallery">
        <?php foreach ($thumbs as $i => $thumb): ?>
            <?php $full = $imgDir . $thumb; ?>
            <img src="<?= $thumbDir . $thumb ?>" data-full="<?= $full ?>" data-index="<?= $i ?>" alt="">
        <?php endforeach; ?>
    </div>

    <div class="lightbox" id="lightbox">
        <span class="close-btn" id="closeBtn">×</span>
        <span class="arrow arrow-left" id="prevBtn">❮</span>
        <span class="arrow arrow-right" id="nextBtn">❯</span>
        <img id="lightbox-img" src="">
    </div>

    <script>
        const images = [...document.querySelectorAll(".gallery img")];
        const lightbox = document.getElementById("lightbox");
        const lightboxImg = document.getElementById("lightbox-img");
        const closeBtn = document.getElementById("closeBtn");
        const nextBtn = document.getElementById("nextBtn");
        const prevBtn = document.getElementById("prevBtn");

        let currentIndex = 0;

        function showImage(index) {
            currentIndex = index;
            lightboxImg.src = images[index].dataset.full;
            lightbox.style.display = "flex";
        }

        images.forEach((img, i) => {
            img.addEventListener("click", () => showImage(i));
        });

        function nextImage() {
            currentIndex = (currentIndex + 1) % images.length;
            showImage(currentIndex);
        }

        function prevImage() {
            currentIndex = (currentIndex - 1 + images.length) % images.length;
            showImage(currentIndex);
        }

        nextBtn.addEventListener("click", nextImage);
        prevBtn.addEventListener("click", prevImage);
        closeBtn.addEventListener("click", () => lightbox.style.display = "none");

        lightbox.addEventListener("click", (e) => {
            if (e.target === lightbox) lightbox.style.display = "none";
        });

        document.addEventListener("keydown", (e) => {
            if (lightbox.style.display !== "flex") return;

            if (e.key === "ArrowRight") nextImage();
            if (e.key === "ArrowLeft") prevImage();
            if (e.key === "Escape") lightbox.style.display = "none";
        });

        // ⭐ Förbättrad swipe-funktion
        let touchStartX = 0;
        let touchEndX = 0;

        function handleSwipe() {
            const diff = touchStartX - touchEndX;

            if (Math.abs(diff) < 50) return;

            if (diff > 0) nextImage();
            else prevImage();
        }

        lightbox.addEventListener("touchstart", (e) => {
            touchStartX = e.changedTouches[0].clientX;
        });

        lightbox.addEventListener("touchmove", (e) => {
            touchEndX = e.changedTouches[0].clientX;
        });

        lightbox.addEventListener("touchend", () => {
            handleSwipe();
        });
    </script>

</body>

</html>