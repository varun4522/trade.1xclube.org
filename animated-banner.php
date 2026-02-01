<?php
// animated-banner.php
// Place this file in your project and include it where you want the banner to appear

// Sample banner data - you can replace this with database data
$banners = [
    [
        'image' => 'banners/banner1.jpg',
        'title' => 'Special Offer!',
        'description' => 'Get 50% bonus on your first deposit',
        'link' => 'promo.html',
        'bg_color' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
    ],
    [
        'image' => 'banners/banner2.jpg',
        'title' => 'New Games Added',
        'description' => 'Try our latest exciting games',
        'link' => 'games.html',
        'bg_color' => 'linear-gradient(135deg, #ff758c 0%, #ff7eb3 100%)'
    ],
    [
        'image' => 'banners/banner3.jpg',
        'title' => 'Refer & Earn',
        'description' => 'Invite friends and get rewards',
        'link' => 'refer.html',
        'bg_color' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)'
    ]
];
?>

<div class="animated-banner-container">
    <div class="animated-banner-slider">
        <?php foreach ($banners as $index => $banner): ?>
        <div class="banner-slide <?php echo $index === 0 ? 'active' : ''; ?>" 
             style="background: <?php echo $banner['bg_color']; ?>">
            <div class="banner-content">
                <h3><?php echo $banner['title']; ?></h3>
                <p><?php echo $banner['description']; ?></p>
                <a href="<?php echo $banner['link']; ?>" class="banner-button">Learn More</a>
            </div>
            <div class="banner-image">
                <img src="<?php echo $banner['image']; ?>" alt="<?php echo $banner['title']; ?>">
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="banner-controls">
        <?php foreach ($banners as $index => $banner): ?>
        <span class="banner-dot <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>"></span>
        <?php endforeach; ?>
    </div>
</div>

<style>
.animated-banner-container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto 20px;
    position: relative;
    overflow: hidden;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    height: 250px;
}

.animated-banner-slider {
    display: flex;
    height: 100%;
    transition: transform 0.8s cubic-bezier(0.645, 0.045, 0.355, 1);
}

.banner-slide {
    min-width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    padding: 0 50px;
    position: relative;
    overflow: hidden;
}

.banner-content {
    flex: 1;
    z-index: 2;
    color: white;
    text-shadow: 0 2px 5px rgba(0,0,0,0.2);
    animation: fadeInLeft 0.8s ease;
}

.banner-content h3 {
    font-size: 2rem;
    margin-bottom: 10px;
    font-weight: 700;
}

.banner-content p {
    font-size: 1.1rem;
    margin-bottom: 20px;
    max-width: 500px;
}

.banner-button {
    display: inline-block;
    padding: 10px 25px;
    background: white;
    color: #333;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.banner-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
}

.banner-image {
    position: absolute;
    right: 50px;
    bottom: 0;
    height: 90%;
    animation: fadeInRight 0.8s ease;
}

.banner-image img {
    height: 100%;
    object-fit: contain;
    filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));
}

.banner-controls {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
}

.banner-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255,255,255,0.5);
    cursor: pointer;
    transition: all 0.3s ease;
}

.banner-dot.active {
    background: white;
    transform: scale(1.2);
}

@keyframes fadeInLeft {
    from { opacity: 0; transform: translateX(-50px); }
    to { opacity: 1; transform: translateX(0); }
}

@keyframes fadeInRight {
    from { opacity: 0; transform: translateX(50px); }
    to { opacity: 1; transform: translateX(0); }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .animated-banner-container {
        height: 200px;
    }
    
    .banner-slide {
        padding: 0 20px;
        flex-direction: column;
        text-align: center;
    }
    
    .banner-content {
        margin-top: 20px;
    }
    
    .banner-image {
        position: relative;
        right: auto;
        height: 60%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const slider = document.querySelector('.animated-banner-slider');
    const slides = document.querySelectorAll('.banner-slide');
    const dots = document.querySelectorAll('.banner-dot');
    let currentIndex = 0;
    let interval;
    
    function goToSlide(index) {
        currentIndex = index;
        slider.style.transform = `translateX(-${currentIndex * 100}%)`;
        
        // Update dots
        dots.forEach(dot => dot.classList.remove('active'));
        dots[currentIndex].classList.add('active');
        
        // Reset animation for current slide
        slides.forEach(slide => slide.classList.remove('active'));
        slides[currentIndex].classList.add('active');
    }
    
    function nextSlide() {
        currentIndex = (currentIndex + 1) % slides.length;
        goToSlide(currentIndex);
    }
    
    // Auto slide every 5 seconds
    function startSlider() {
        interval = setInterval(nextSlide, 5000);
    }
    
    // Click on dot to go to specific slide
    dots.forEach(dot => {
        dot.addEventListener('click', function() {
            const slideIndex = parseInt(this.getAttribute('data-slide'));
            goToSlide(slideIndex);
            clearInterval(interval);
            startSlider();
        });
    });
    
    // Start the slider
    startSlider();
    
    // Pause on hover
    slider.addEventListener('mouseenter', () => clearInterval(interval));
    slider.addEventListener('mouseleave', startSlider);
});
</script>