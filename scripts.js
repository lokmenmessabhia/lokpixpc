document.addEventListener('DOMContentLoaded', function() {
    let currentSlide = 0;
    const slides = document.querySelectorAll('.slide');
    const totalSlides = slides.length;
    const slideInterval = 10000; // 10 seconds

    function showSlide(index) {
        const offset = -index * 100; // Offset to show the current slide
        document.querySelector('.slides').style.transform = `translateX(${offset}%)`;
    }

    function nextSlide() {
        currentSlide = (currentSlide + 1) % totalSlides;
        showSlide(currentSlide);
    }

    function prevSlide() {
        currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
        showSlide(currentSlide);
    }

    // Initial call to show the first slide
    showSlide(currentSlide);

    // Set up automatic slide transition
    const autoSlide = setInterval(nextSlide, slideInterval);

    // Add event listeners for the previous and next buttons
    document.querySelector('.prev').addEventListener('click', () => {
        clearInterval(autoSlide); // Stop auto sliding
        prevSlide();
        setInterval(nextSlide, slideInterval); // Restart auto sliding
    });
    
    document.querySelector('.next').addEventListener('click', () => {
        clearInterval(autoSlide); // Stop auto sliding
        nextSlide();
        setInterval(nextSlide, slideInterval); // Restart auto sliding
    });
});
