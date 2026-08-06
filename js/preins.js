(() => {
    'use strict';

    let currentLang = localStorage.getItem('lang') || 'fr';
    const langToggle = document.getElementById('langToggle');
    if (!langToggle) return;
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

    if (hamburger && nav) {
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
    }

    // ---------- Scroll Reveal ----------
    document.querySelectorAll('.step-card, .doc-card, .form-container').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(24px)';
        el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    });

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.08 });

    document.querySelectorAll('.step-card, .doc-card, .form-container').forEach(el => revealObserver.observe(el));

    // ---------- Scroll to Top ----------
    const scrollTopBtn = document.getElementById('scrollTop');
    if (scrollTopBtn) {
        window.addEventListener('scroll', () => scrollTopBtn.classList.toggle('visible', window.scrollY > 400));
        scrollTopBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }

    // ---------- Modal ----------
    const modal = document.getElementById('successModal');
    if (modal) {
        const closeModal = () => modal.classList.remove('open');
        modal.querySelector('.modal-close')?.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    }

    // ---------- Step Form ----------
    const form = document.getElementById('preinsForm');
    if (!form) return;

    form.setAttribute('novalidate', '');

    const steps = form.querySelectorAll('.form-step');
    const prevBtn = document.getElementById('prevStep');
    const nextBtn = document.getElementById('nextStep');
    const submitBtn = document.getElementById('submitBtn');
    let currentStep = 1;
    const totalSteps = 3;

    function showStep(step) {
        steps.forEach(s => s.classList.remove('active'));
        const target = form.querySelector(`.form-step[data-step="${step}"]`);
        if (target) target.classList.add('active');
        prevBtn.style.display = step === 1 ? 'none' : 'inline-block';
        nextBtn.style.display = step === totalSteps ? 'none' : 'inline-block';
        submitBtn.style.display = step === totalSteps ? 'inline-block' : 'none';
    }

    function clearErrors(container) {
        container.querySelectorAll('.field-error').forEach(el => el.remove());
        container.querySelectorAll('input, textarea, select').forEach(el => {
            el.style.borderColor = '';
        });
    }

    function showError(field, msg) {
        field.style.borderColor = '#e74c3c';
        const parent = field.closest('.form-row') || field.parentElement;
        if (!parent.querySelector('.field-error')) {
            const err = document.createElement('span');
            err.className = 'field-error';
            err.style.cssText = 'color:#e74c3c;font-size:0.78rem;margin-top:4px;display:block;';
            err.textContent = msg;
            parent.appendChild(err);
        }
    }

    function validateStep(step) {
        const current = form.querySelector(`.form-step[data-step="${step}"]`);
        if (!current) return true;
        clearErrors(current);

        const textInputs = current.querySelectorAll('input[required]:not([type="file"]):not([type="radio"]), textarea[required], select[required]');
        const fileInputs = current.querySelectorAll('input[type="file"][required]');
        let valid = true;

        textInputs.forEach(field => {
            const val = field.value.trim();
            if (!val) {
                const label = field.closest('.form-row')?.querySelector('label');
                const name = label ? label.textContent.replace('*', '').trim() : 'Ce champ';
                showError(field, currentLang === 'fr' ? `${name} est requis` : `${name} مطلوب`);
                valid = false;
            }
            if (field.type === 'email' && val && !val.includes('@')) {
                showError(field, currentLang === 'fr' ? 'Email invalide' : 'بريد إلكتروني غير صحيح');
                valid = false;
            }
        });

        fileInputs.forEach(field => {
            if (!field.files || field.files.length === 0) {
                const box = field.closest('.file-upload-box');
                if (box) {
                    const p = box.querySelector('p');
                    const name = p ? p.textContent.replace('*', '').trim() : 'Ce fichier';
                    showError(box, currentLang === 'fr' ? `${name} est requis` : `${name} مطلوب`);
                }
                valid = false;
            }
        });

        return valid;
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (validateStep(currentStep)) {
                currentStep++;
                showStep(currentStep);
            }
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            currentStep--;
            showStep(currentStep);
            clearErrors(form.querySelector(`.form-step[data-step="${currentStep}"]`));
        });
    }

    // Clear errors on interaction
    form.querySelectorAll('input, textarea, select, .file-upload-box input[type="file"]').forEach(field => {
        const clear = () => {
            field.style.borderColor = '';
            const parent = field.closest('.form-row') || field.parentElement;
            parent.querySelectorAll('.field-error').forEach(el => el.remove());
            if (field.closest('.file-upload-box')) {
                field.closest('.file-upload-box').style.borderColor = '';
            }
        };
        field.addEventListener('input', clear);
        field.addEventListener('change', clear);
    });

    // ---------- Health condition toggle ----------
    const santeRadios = document.querySelectorAll('input[name="sante"]');
    const santeDetailRow = document.getElementById('santeDetailRow');
    if (santeRadios.length && santeDetailRow) {
        santeRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                santeDetailRow.style.display = radio.value === 'oui' ? 'block' : 'none';
            });
        });
    }

    // ---------- File upload feedback ----------
    document.querySelectorAll('.file-upload-box input[type="file"]').forEach(input => {
        input.addEventListener('change', () => {
            const box = input.closest('.file-upload-box');
            if (box) {
                if (input.files.length > 0) {
                    box.classList.add('has-file');
                    box.style.borderColor = '';
                    box.querySelector('span').textContent = input.files[0].name;
                    box.querySelectorAll('.field-error').forEach(el => el.remove());
                } else {
                    box.classList.remove('has-file');
                    box.querySelector('span').textContent = currentLang === 'fr' ? 'Cliquez pour ajouter le fichier' : 'انقر لإضافة الملف';
                }
            }
        });
    });

    // ---------- Form submit ----------
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!validateStep(currentStep)) return;

        const btn = submitBtn;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const fields = [
            'nom', 'prenom', 'dateNaissance', 'lieuNaissance', 'nationalite',
            'classeActuelle', 'etablissementActuel', 'cycleSouhaite',
            'parentNom', 'parentPrenom', 'lienParente', 'parentTel',
            'parentEmail', 'parentAdresse', 'parentProfession'
        ];
        const parts = fields.map(f => {
            const el = document.getElementById(f);
            return encodeURIComponent(f) + '=' + encodeURIComponent(el ? el.value : '');
        });
        const sante = document.querySelector('input[name="sante"]:checked');
        parts.push('sante=' + encodeURIComponent(sante ? sante.value : 'non'));
        const civilite = document.querySelector('input[name="civilite"]:checked');
        parts.push('civilite=' + encodeURIComponent(civilite ? civilite.value : 'Mme'));
        const detail = document.getElementById('santeDetail');
        parts.push('santeDetail=' + encodeURIComponent(detail ? detail.value : ''));

        fetch('inscrire.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: parts.join('&')
        })
        .then(r => r.json().then(j => ({ ok: r.ok, j })))
        .then(({ ok, j }) => {
            if (!ok || !j || !j.ok) {
                throw new Error((j && j.error) || "Erreur lors de l'envoi.");
            }
            form.reset();
            currentStep = 1;
            showStep(1);

            document.querySelectorAll('.file-upload-box').forEach(box => {
                box.classList.remove('has-file');
                box.style.borderColor = '';
                box.querySelector('span').textContent = currentLang === 'fr' ? 'Cliquez pour ajouter le fichier' : 'انقر لإضافة الملف';
            });
            if (santeDetailRow) santeDetailRow.style.display = 'none';
            document.querySelectorAll('.field-error').forEach(el => el.remove());

            if (modal) modal.classList.add('open');
        })
        .catch(err => {
            const current = form.querySelector('.form-step[data-step="' + currentStep + '"]');
            const parent = current.querySelector('.form-step-header') || current;
            if (!parent.querySelector('.field-error')) {
                const span = document.createElement('span');
                span.className = 'field-error';
                span.style.cssText = 'color:#e74c3c;font-size:0.85rem;margin-top:8px;display:block;';
                span.textContent = err.message || "Erreur lors de l'envoi.";
                parent.appendChild(span);
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });

    showStep(1);

})();
