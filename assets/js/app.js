document.addEventListener('DOMContentLoaded', () => {
    const flashMessages = document.querySelectorAll('.flash');
    flashMessages.forEach((flash) => {
        setTimeout(() => {
            flash.classList.add('hide');
        }, 4500);
    });

    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const savedTheme = localStorage.getItem('pcp-theme');
    const body = document.body;
    const themeToggle = document.getElementById('themeToggle');

    const applyTheme = (theme) => {
        body.setAttribute('data-theme', theme);
        localStorage.setItem('pcp-theme', theme);
    };

    if (savedTheme) {
        applyTheme(savedTheme);
    } else {
        applyTheme(prefersDark ? 'dark' : 'light');
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const next = body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(next);
        });
    }

    const revealItems = document.querySelectorAll('.reveal');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('show');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    revealItems.forEach(el => observer.observe(el));
});
