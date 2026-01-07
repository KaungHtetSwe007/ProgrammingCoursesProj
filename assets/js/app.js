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

    const instructorCards = document.querySelectorAll('.instructor-card[data-link]');
    instructorCards.forEach(card => {
        const link = card.dataset.link;
        if (!link) return;

        card.addEventListener('click', (event) => {
            if (event.target.closest('form') || event.target.closest('button') || event.target.closest('a')) {
                return;
            }
            window.location = link;
        });

        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                window.location = link;
            }
        });
    });

    const courseCards = document.querySelectorAll('.course-card-pro[data-link]');
    courseCards.forEach(card => {
        const link = card.dataset.link;
        if (!link) return;
        card.addEventListener('click', (event) => {
            if (event.target.closest('a') || event.target.closest('button')) {
                return;
            }
            window.location = link;
        });
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                window.location = link;
            }
        });
    });

    const replyToggles = document.querySelectorAll('.reply-toggle');
    replyToggles.forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.target;
            const form = document.getElementById(targetId);
            if (!form) return;
            form.classList.toggle('is-hidden');
            const textarea = form.querySelector('textarea');
            if (!form.classList.contains('is-hidden') && textarea) {
                textarea.focus();
            }
        });
    });

    const attachmentInputs = document.querySelectorAll('.attachment-input');
    attachmentInputs.forEach((input) => {
        const label = input.closest('.attach-label');
        if (!label) return;
        const textNode = label.querySelector('.attach-text');
        const defaultText = textNode ? textNode.textContent : '';
        input.addEventListener('change', () => {
            if (!textNode) return;
            const file = input.files && input.files[0];
            textNode.textContent = file ? file.name : defaultText;
        });
    });

    const carouselState = {};

    const getCarouselState = (id) => {
        if (carouselState[id]) return carouselState[id];
        const el = document.getElementById(id);
        if (!el) return null;
        const state = { el, buttons: [] };
        state.update = () => {
            const maxScroll = Math.max(0, el.scrollWidth - el.clientWidth - 6);
            state.buttons.forEach((btn) => {
                const isNext = btn.dataset.direction === 'next';
                btn.disabled = isNext ? el.scrollLeft >= maxScroll : el.scrollLeft <= 4;
            });
        };
        el.addEventListener('scroll', state.update);
        window.addEventListener('resize', state.update);
        setTimeout(state.update, 80);
        carouselState[id] = state;
        return state;
    };

    document.querySelectorAll('.carousel-arrow[data-target]').forEach((button) => {
        const targetId = button.dataset.target;
        const direction = button.dataset.direction === 'next' ? 1 : -1;
        const state = getCarouselState(targetId);
        if (!state) return;
        state.buttons.push(button);

        button.addEventListener('click', () => {
            const distance = Math.max(state.el.clientWidth * 0.8, 260);
            state.el.scrollBy({ left: direction * distance, behavior: 'smooth' });
        });

        state.update();
    });
});
