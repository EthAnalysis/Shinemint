document.addEventListener('DOMContentLoaded', () => {

    // --- Theme Toggle Functionality ---
    const themeBtn = document.getElementById('theme-toggle');
    const sunIcon = document.querySelector('.sun-icon');
    const moonIcon = document.querySelector('.moon-icon');

    // Check local storage for preference, default to dark
    let isDarkMode = localStorage.getItem('theme') !== 'light';

    function updateThemeUI() {
        if (isDarkMode) {
            document.body.classList.add('dark-theme');
            sunIcon.classList.remove('hidden');
            moonIcon.classList.add('hidden');
        } else {
            document.body.classList.remove('dark-theme');
            sunIcon.classList.add('hidden');
            moonIcon.classList.remove('hidden');
        }
    }

    // Initial Setup
    updateThemeUI();

    themeBtn.addEventListener('click', () => {
        isDarkMode = !isDarkMode;
        localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
        updateThemeUI();
    });

    // --- Mobile Menu Toggle ---
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileNav = document.getElementById('mobile-nav');
    let isMenuOpen = false;

    mobileMenuBtn.addEventListener('click', () => {
        isMenuOpen = !isMenuOpen;
        if (isMenuOpen) {
            mobileNav.classList.add('active');
            // Animate hamburger to X (basic implementation)
            const spans = mobileMenuBtn.querySelectorAll('span');
            spans[0].style.transform = 'translateY(8px) rotate(45deg)';
            spans[1].style.opacity = '0';
            spans[2].style.transform = 'translateY(-8px) rotate(-45deg)';
            document.body.style.overflow = 'hidden'; // prevent scrolling
        } else {
            closeMobileMenu();
        }
    });

    function closeMobileMenu() {
        isMenuOpen = false;
        mobileNav.classList.remove('active');
        const spans = mobileMenuBtn.querySelectorAll('span');
        spans[0].style.transform = 'none';
        spans[1].style.opacity = '1';
        spans[2].style.transform = 'none';
        document.body.style.overflow = '';
    }

    // Close mobile menu on link click
    document.querySelectorAll('.mobile-nav-link').forEach(link => {
        link.addEventListener('click', closeMobileMenu);
    });

    // --- Navbar Scroll Effect ---
    const navbar = document.getElementById('navbar');

    window.addEventListener('scroll', () => {
        if (window.scrollY > 20) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // --- Scroll Reveal Animations ---
    const revealElements = document.querySelectorAll('.reveal');

    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
                observer.unobserve(entry.target); // Stop observing once revealed
            }
        });
    }, {
        root: null,
        threshold: 0.1, // Trigger when 10% of element is visible
        rootMargin: '0px 0px -50px 0px'
    });

    revealElements.forEach(el => {
        revealObserver.observe(el);
    });
});

// --- Clipboard Copy functionality ---
function copyToClipboard(text, btnElement) {
    navigator.clipboard.writeText(text).then(() => {
        // Find existing text to restore later
        const originalText = btnElement.innerText;
        // Check if there's an SVG inside to preserve (like the outline button)
        const hasSvg = btnElement.querySelector('svg') !== null;
        let originalHTML = btnElement.innerHTML;

        btnElement.innerText = 'Email Copied!';
        btnElement.style.backgroundColor = '#10b981'; // Success green
        btnElement.style.color = '#fff';
        btnElement.style.borderColor = '#10b981';

        setTimeout(() => {
            btnElement.innerHTML = originalHTML; // restore including SVGs if any
            btnElement.style.backgroundColor = '';
            btnElement.style.color = '';
            btnElement.style.borderColor = '';
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy text: ', err);
        // Fallback or alert
        alert("Please contact us at info@shinemint.com");
    });
}

/* ==========================================================================
   Parametric Institutional Motion
   ========================================================================== */
(() => {
    const bg = document.getElementById('wire-bg');
    if (!bg) return;
    const paths = [...bg.querySelectorAll('path.p')];

    // Cache original d for each path
    const originals = new Map();
    paths.forEach(p => originals.set(p, p.getAttribute('d')));

    // Utilities: parse a very simple cubic path "M x,y C x,y x,y x,y"
    function parseCubicD(d) {
        const nums = d.match(/-?\d+(\.\d+)?/g).map(Number);
        return {
            mx: nums[0], my: nums[1],
            x1: nums[2], y1: nums[3],
            x2: nums[4], y2: nums[5],
            x: nums[6], y: nums[7]
        };
    }

    function buildCubicD(o) {
        return `M${o.mx},${o.my} C${o.x1},${o.y1} ${o.x2},${o.y2} ${o.x},${o.y}`;
    }

    let tx = 0, ty = 0;
    let cx = 0, cy = 0;

    function tick() {
        cx += (tx - cx) * 0.06;
        cy += (ty - cy) * 0.06;

        bg.style.transform = `translate3d(${cx * 10}px, ${cy * 10}px, 0)`;

        paths.forEach(p => {
            const base = parseCubicD(originals.get(p));
            const amp = Number(p.dataset.amp || 6);

            base.y1 = base.y1 + cy * amp;
            base.y2 = base.y2 - cy * amp;
            base.x1 = base.x1 + cx * (amp * 0.6);
            base.x2 = base.x2 + cx * (amp * 0.6);

            p.setAttribute('d', buildCubicD(base));
        });

        requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);

    window.addEventListener('mousemove', (e) => {
        const nx = (e.clientX / window.innerWidth) * 2 - 1;
        const ny = (e.clientY / window.innerHeight) * 2 - 1;
        tx = nx;
        ty = ny;
    }, { passive: true });

    window.addEventListener('deviceorientation', (e) => {
        if (e.gamma == null || e.beta == null) return;
        tx = Math.max(-1, Math.min(1, e.gamma / 30));
        ty = Math.max(-1, Math.min(1, e.beta / 45));
    }, { passive: true });

    const sections = document.querySelectorAll('section');
    const io = new IntersectionObserver((entries) => {
        const active = entries.some(e => e.isIntersecting);
        bg.classList.toggle('pulsing', active);
    }, { threshold: 0.20 });
    sections.forEach(s => io.observe(s));

    function updatePulseSpeed() {
        const doc = document.documentElement;
        const max = (doc.scrollHeight - doc.clientHeight) || 1;
        const t = doc.scrollTop / max;
        const dur = (2.6 - (1.2 * t)).toFixed(2) + 's';
        bg.style.setProperty('--pulseDur', dur);
    }
    window.addEventListener('scroll', updatePulseSpeed, { passive: true });
    updatePulseSpeed();

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        tx = ty = cx = cy = 0;
        bg.style.transform = 'none';
        paths.forEach(p => p.setAttribute('d', originals.get(p)));
    }
})();
