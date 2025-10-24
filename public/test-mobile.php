<?php
require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Layout;

// Simple test page for mobile libraries
$pageTitle = 'Mobile Libraries Test';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php Layout::renderHead($pageTitle, 'test', true); ?>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .test-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .test-section h2 {
            margin-top: 0;
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status.loaded { background: #10b981; color: white; }
        .status.error { background: #ef4444; color: white; }
        
        /* Swiper Demo */
        .swiper {
            width: 100%;
            height: 300px;
            border-radius: 8px;
            overflow: hidden;
        }
        .swiper-slide {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: white;
        }
        .swiper-slide:nth-child(1) { background: linear-gradient(45deg, #f093fb 0%, #f5576c 100%); }
        .swiper-slide:nth-child(2) { background: linear-gradient(45deg, #4facfe 0%, #00f2fe 100%); }
        .swiper-slide:nth-child(3) { background: linear-gradient(45deg, #43e97b 0%, #38f9d7 100%); }
        .swiper-slide:nth-child(4) { background: linear-gradient(45deg, #fa709a 0%, #fee140 100%); }
        
        /* Hammer.js Demo */
        .gesture-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px;
            text-align: center;
            border-radius: 8px;
            user-select: none;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        /* PhotoSwipe Gallery */
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
        .gallery a {
            display: block;
            overflow: hidden;
            border-radius: 8px;
        }
        .gallery img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            transition: transform 0.3s;
        }
        .gallery img:hover {
            transform: scale(1.1);
        }
        
        /* QR Code */
        #qrcode {
            display: inline-block;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        /* Lottie Animation */
        #lottie {
            width: 200px;
            height: 200px;
            margin: 0 auto;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #764ba2;
        }
        
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        
        .gesture-log {
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="test-section">
        <h1>📱 Mobile Libraries Test Page</h1>
        <p>Test all mobile-optimized libraries on this page. Works on desktop too!</p>
        
        <div class="info-box">
            <strong>💡 Best on mobile:</strong> Open this page on your phone/tablet for the full experience!
        </div>
    </div>

    <!-- Library Status -->
    <div class="test-section">
        <h2>📦 Library Status</h2>
        <div id="library-status">Checking libraries...</div>
    </div>

    <!-- Device Detection -->
    <div class="test-section">
        <h2>📱 Mobile Detect - Device Detection</h2>
        <div id="device-info">
            <p><strong>User Agent:</strong> <span id="ua"></span></p>
            <p><strong>Device Type:</strong> <span id="device-type"></span></p>
            <p><strong>Is Mobile:</strong> <span id="is-mobile"></span></p>
            <p><strong>Is Tablet:</strong> <span id="is-tablet"></span></p>
        </div>
    </div>

    <!-- Swiper Carousel -->
    <div class="test-section">
        <h2>🎠 Swiper - Touch Carousel</h2>
        <p>Swipe left/right on mobile, or use arrows on desktop</p>
        
        <div class="swiper mySwiper">
            <div class="swiper-wrapper">
                <div class="swiper-slide">Slide 1 - Swipe Me!</div>
                <div class="swiper-slide">Slide 2 - Keep Going!</div>
                <div class="swiper-slide">Slide 3 - Almost There!</div>
                <div class="swiper-slide">Slide 4 - Amazing!</div>
            </div>
            <div class="swiper-pagination"></div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
    </div>

    <!-- Hammer.js Gestures -->
    <div class="test-section">
        <h2>👆 Hammer.js - Touch Gestures</h2>
        <p>Try swiping, tapping, double-tapping, or pinching (on touch devices)</p>
        
        <div class="gesture-box" id="gesture-box">
            <h3>Touch or Swipe Me!</h3>
            <p>Supports: tap, doubletap, swipe, pan, pinch</p>
        </div>
        
        <div class="gesture-log" id="gesture-log">
            Gesture log will appear here...
        </div>
    </div>

    <!-- QR Code -->
    <div class="test-section">
        <h2>📷 QRCode.js - Generate QR Codes</h2>
        <p>Scan this QR code with your phone camera!</p>
        
        <div style="text-align: center;">
            <div id="qrcode"></div>
            <p style="margin-top: 10px;">Points to: <strong>https://hub.woodsonisd.net</strong></p>
        </div>
    </div>

    <!-- PhotoSwipe Gallery -->
    <div class="test-section">
        <h2>🖼️ PhotoSwipe - Touch Gallery</h2>
        <p>Tap any image to view in full screen with pinch zoom (mobile-optimized)</p>
        
        <div class="gallery pswp-gallery" id="gallery">
            <a href="https://picsum.photos/800/600?random=1" 
               data-pswp-width="800" 
               data-pswp-height="600">
                <img src="https://picsum.photos/200/150?random=1" alt="Image 1">
            </a>
            <a href="https://picsum.photos/800/600?random=2" 
               data-pswp-width="800" 
               data-pswp-height="600">
                <img src="https://picsum.photos/200/150?random=2" alt="Image 2">
            </a>
            <a href="https://picsum.photos/800/600?random=3" 
               data-pswp-width="800" 
               data-pswp-height="600">
                <img src="https://picsum.photos/200/150?random=3" alt="Image 3">
            </a>
            <a href="https://picsum.photos/800/600?random=4" 
               data-pswp-width="800" 
               data-pswp-height="600">
                <img src="https://picsum.photos/200/150?random=4" alt="Image 4">
            </a>
            <a href="https://picsum.photos/800/600?random=5" 
               data-pswp-width="800" 
               data-pswp-height="600">
                <img src="https://picsum.photos/200/150?random=5" alt="Image 5">
            </a>
            <a href="https://picsum.photos/800/600?random=6" 
               data-pswp-width="800" 
               data-pswp-height="600">
                <img src="https://picsum.photos/200/150?random=6" alt="Image 6">
            </a>
        </div>
    </div>

    <!-- Lottie Animation -->
    <div class="test-section">
        <h2>🎬 Lottie - Lightweight Animations</h2>
        <p>Smooth vector animations perfect for mobile</p>
        
        <div id="lottie"></div>
        <div style="text-align: center; margin-top: 15px;">
            <button class="btn" onclick="lottieAnimation.play()">Play</button>
            <button class="btn" onclick="lottieAnimation.pause()">Pause</button>
            <button class="btn" onclick="lottieAnimation.stop()">Stop</button>
        </div>
    </div>

    <!-- Pull to Refresh -->
    <div class="test-section">
        <h2>↓ PulltoRefresh - iOS-style Refresh</h2>
        <div class="info-box">
            <strong>📱 Mobile Only:</strong> Pull down from the top of this page on mobile to refresh!
        </div>
        <p>Last refresh: <span id="last-refresh">Never</span></p>
    </div>

    <script>
        // Check library status
        const libraries = {
            'Hammer.js': typeof Hammer !== 'undefined',
            'Swiper': typeof Swiper !== 'undefined',
            'MobileDetect': typeof MobileDetect !== 'undefined',
            'QRCode': typeof QRCode !== 'undefined',
            'PhotoSwipe': typeof PhotoSwipe !== 'undefined',
            'Lottie': typeof lottie !== 'undefined',
            'PulltoRefresh': typeof PullToRefresh !== 'undefined',
            'FastClick': typeof FastClick !== 'undefined',
            'Vibrant': typeof Vibrant !== 'undefined'
        };

        const statusHtml = Object.entries(libraries).map(([name, loaded]) => {
            const statusClass = loaded ? 'loaded' : 'error';
            const statusText = loaded ? '✓ Loaded' : '✗ Not Loaded';
            return `<div>${name} <span class="status ${statusClass}">${statusText}</span></div>`;
        }).join('');

        document.getElementById('library-status').innerHTML = statusHtml;

        // Mobile Detection
        const userAgent = navigator.userAgent;
        document.getElementById('ua').textContent = userAgent;

        if (typeof MobileDetect !== 'undefined') {
            const md = new MobileDetect(userAgent);
            document.getElementById('is-mobile').textContent = md.mobile() ? 'Yes (' + md.mobile() + ')' : 'No';
            document.getElementById('is-tablet').textContent = md.tablet() ? 'Yes (' + md.tablet() + ')' : 'No';
            
            let deviceType = 'Desktop';
            if (md.phone()) deviceType = 'Phone';
            else if (md.tablet()) deviceType = 'Tablet';
            else if (md.mobile()) deviceType = 'Mobile';
            
            document.getElementById('device-type').textContent = deviceType;
        }

        // Initialize Swiper
        if (typeof Swiper !== 'undefined') {
            const swiper = new Swiper('.mySwiper', {
                slidesPerView: 1,
                spaceBetween: 30,
                loop: true,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                }
            });
        }

        // Hammer.js Gestures
        if (typeof Hammer !== 'undefined') {
            const gestureBox = document.getElementById('gesture-box');
            const gestureLog = document.getElementById('gesture-log');
            const hammer = new Hammer(gestureBox);
            
            // Enable pinch and rotate
            hammer.get('pinch').set({ enable: true });
            hammer.get('rotate').set({ enable: true });
            
            function logGesture(type, data = '') {
                const time = new Date().toLocaleTimeString();
                const logEntry = `[${time}] ${type} ${data}\n`;
                gestureLog.textContent = logEntry + gestureLog.textContent;
            }
            
            hammer.on('tap', () => {
                logGesture('TAP', '- Single tap detected');
                gestureBox.style.transform = 'scale(0.95)';
                setTimeout(() => { gestureBox.style.transform = 'scale(1)'; }, 100);
            });
            
            hammer.on('doubletap', () => {
                logGesture('DOUBLETAP', '- Double tap detected');
                gestureBox.style.background = 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)';
                setTimeout(() => {
                    gestureBox.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                }, 500);
            });
            
            hammer.on('swipeleft', () => {
                logGesture('SWIPE', '← Left swipe');
                gestureBox.style.transform = 'translateX(-20px)';
                setTimeout(() => { gestureBox.style.transform = 'translateX(0)'; }, 200);
            });
            
            hammer.on('swiperight', () => {
                logGesture('SWIPE', '→ Right swipe');
                gestureBox.style.transform = 'translateX(20px)';
                setTimeout(() => { gestureBox.style.transform = 'translateX(0)'; }, 200);
            });
            
            hammer.on('swipeup', () => {
                logGesture('SWIPE', '↑ Up swipe');
            });
            
            hammer.on('swipedown', () => {
                logGesture('SWIPE', '↓ Down swipe');
            });
            
            hammer.on('pinch', (e) => {
                logGesture('PINCH', `scale: ${e.scale.toFixed(2)}`);
            });
            
            hammer.on('pan', (e) => {
                if (Math.abs(e.deltaX) > 50 || Math.abs(e.deltaY) > 50) {
                    logGesture('PAN', `deltaX: ${e.deltaX.toFixed(0)}, deltaY: ${e.deltaY.toFixed(0)}`);
                }
            });
        }

        // QR Code
        if (typeof QRCode !== 'undefined') {
            new QRCode(document.getElementById("qrcode"), {
                text: "https://hub.woodsonisd.net",
                width: 200,
                height: 200,
                colorDark: "#667eea",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }

        // PhotoSwipe Gallery
        if (typeof PhotoSwipe !== 'undefined') {
            import('https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/photoswipe-lightbox.esm.min.js')
                .then(module => {
                    const PhotoSwipeLightbox = module.default;
                    const lightbox = new PhotoSwipeLightbox({
                        gallery: '#gallery',
                        children: 'a',
                        pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/photoswipe.esm.min.js')
                    });
                    lightbox.init();
                });
        }

        // Lottie Animation (using a public animation)
        let lottieAnimation;
        if (typeof lottie !== 'undefined') {
            lottieAnimation = lottie.loadAnimation({
                container: document.getElementById('lottie'),
                renderer: 'svg',
                loop: true,
                autoplay: true,
                path: 'https://assets2.lottiefiles.com/packages/lf20_u4yrau.json' // Success checkmark animation
            });
        }

        // Pull to Refresh
        if (typeof PullToRefresh !== 'undefined') {
            PullToRefresh.init({
                mainElement: 'body',
                onRefresh() {
                    return new Promise((resolve) => {
                        setTimeout(() => {
                            document.getElementById('last-refresh').textContent = new Date().toLocaleTimeString();
                            if (typeof TheHub !== 'undefined' && TheHub.notify) {
                                TheHub.notify('Page refreshed! 🎉', 'success');
                            }
                            resolve();
                        }, 1500);
                    });
                }
            });
        }

        // FastClick (removes 300ms tap delay on mobile)
        if (typeof FastClick !== 'undefined' && 'addEventListener' in document) {
            FastClick.attach(document.body);
        }
    </script>

    <?php Layout::renderModernInit(); ?>
</body>
</html>
