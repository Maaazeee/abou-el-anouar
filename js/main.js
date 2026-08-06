(() => {
    'use strict';

    let currentLang = localStorage.getItem('lang') || 'fr';
    const langToggle = document.getElementById('langToggle');
    const langFr = langToggle.querySelector('.lang-fr');
    const langAr = langToggle.querySelector('.lang-ar');

    function switchLang(lang) {
        currentLang = lang;
        localStorage.setItem('lang', lang);

        document.querySelectorAll('[data-fr]').forEach(el => {
            const key = lang === 'ar' ? 'data-ar' : 'data-fr';
            const text = el.getAttribute(key);
            if (text) {
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    el.placeholder = text;
                } else if (el.tagName === 'OPTION') {
                    el.textContent = text;
                } else {
                    el.innerHTML = text;
                }
            }
        });

        document.body.classList.toggle('rtl', lang === 'ar');
        document.documentElement.lang = lang;
        document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';

        langFr.classList.toggle('active-lang', lang === 'fr');
        langAr.classList.toggle('active-lang', lang === 'ar');
    }

    langToggle.addEventListener('click', () => {
        switchLang(currentLang === 'fr' ? 'ar' : 'fr');
    });

    switchLang(currentLang);

    // ---------- Mobile Menu ----------
    const hamburger = document.getElementById('hamburger');
    const nav = document.getElementById('nav');

    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        nav.classList.toggle('open');
    });

    document.querySelectorAll('.nav-list a').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('active');
            nav.classList.remove('open');
        });
    });

    // ---------- Active Nav Link ----------
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-list a');

    function updateActiveLink() {
        let current = '';
        sections.forEach(section => {
            const top = section.offsetTop - 160;
            if (window.scrollY >= top) {
                current = section.getAttribute('id');
            }
        });
        navLinks.forEach(link => {
            link.classList.toggle('active', link.getAttribute('href') === `#${current}`);
        });
    }

    if (sections.length) {
        window.addEventListener('scroll', updateActiveLink);
        updateActiveLink();
    }

    // ---------- Scroll Reveal ----------
    const revealElements = document.querySelectorAll(
        '.stat-item, .value-card, .niveau-card, .service-card, .faq-item, .contact-item, .contact-form'
    );

    if (revealElements.length) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.08 });

        revealElements.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(24px)';
            el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(el);
        });
    }

    // ---------- FAQ Accordion ----------
    document.querySelectorAll('.faq-question').forEach(btn => {
        btn.addEventListener('click', () => {
            const item = btn.parentElement;
            const isOpen = item.classList.contains('open');
            document.querySelectorAll('.faq-item.open').forEach(openItem => {
                openItem.classList.remove('open');
            });
            if (!isOpen) {
                item.classList.add('open');
            }
        });
    });

    // ---------- Modal ----------
    const modal = document.getElementById('successModal');
    const modalClose = modal ? modal.querySelector('.modal-close') : null;

    function openModal() {
        if (modal) {
            modal.classList.add('open');
        }
    }

    function closeModal() {
        if (modal) {
            modal.classList.remove('open');
        }
    }

    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }

    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    }

    // ---------- Scroll to Top ----------
    const scrollTopBtn = document.getElementById('scrollTop');

    if (scrollTopBtn) {
        window.addEventListener('scroll', () => {
            scrollTopBtn.classList.toggle('visible', window.scrollY > 400);
        });

        scrollTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ---------- Contact Form ----------
    const contactForm = document.getElementById('contactForm');

    if (contactForm) {
        contactForm.setAttribute('novalidate', '');
        contactForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const btn = contactForm.querySelector('.btn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            // Validate all required fields
            let valid = true;
            contactForm.querySelectorAll('[required]').forEach(field => {
                field.style.borderColor = '#e8e4d8';
                if (!field.value.trim()) {
                    field.style.borderColor = '#e74c3c';
                    valid = false;
                }
            });

            if (!valid) {
                btn.disabled = false;
                btn.innerHTML = originalText;
                return;
            }

            // Simulate sending
            setTimeout(() => {
                contactForm.reset();
                btn.innerHTML = originalText;
                btn.disabled = false;
                openModal();
            }, 800);
        });

        // Clear error state on input
        contactForm.querySelectorAll('input, textarea, select').forEach(field => {
            field.addEventListener('input', () => {
                field.style.borderColor = '#e8e4d8';
            });
        });
    }

})();
