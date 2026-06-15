// Smooth scrolling
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});

// Navbar scroll effect
window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 100) {
        navbar.style.background = 'rgba(15, 23, 42, 0.98)';
    } else {
        navbar.style.background = 'rgba(15, 23, 42, 0.95)';
    }
});

// Form validation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const inputs = this.querySelectorAll('input[required], select[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.style.borderColor = '#ef4444';
                isValid = false;
            } else {
                input.style.borderColor = 'rgba(255,255,255,0.2)';
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields');
        }
    });
});

// API calls for live weather
async function getWeather(city) {
    try {
        const response = await fetch(`/api.php?path=weather&city=${encodeURIComponent(city)}`);
        const data = await response.json();
        if (data.success) {
            return data.data;
        }
    } catch (error) {
        console.error('Weather API error:', error);
    }
    return null;
}

// Search flights
async function searchFlights(origin, destination, date) {
    try {
        const response = await fetch(`/api.php?path=flights&origin=${origin}&destination=${destination}&date=${date}`);
        const data = await response.json();
        if (data.success) {
            return data.data;
        }
    } catch (error) {
        console.error('Flight API error:', error);
    }
    return null;
}

// Animation on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, observerOptions);

document.querySelectorAll('.feature-card, .budget-card, .step-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'all 0.6s ease';
    observer.observe(el);
});

// Add visible class
const style = document.createElement('style');
style.textContent = `
    .feature-card.visible, .budget-card.visible, .step-card.visible {
        opacity: 1 !important;
        transform: translateY(0) !important;
    }
`;
document.head.appendChild(style);