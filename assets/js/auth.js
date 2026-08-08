document.addEventListener('DOMContentLoaded', () => {
    const loginPassword = document.getElementById('login-password');
    const toggleLoginPassword = document.getElementById('toggle-login-password');
    const loginForm = document.querySelector('form[action="/auth"]');
    const loginSubmit = document.getElementById('login-submit');

    if (loginPassword && toggleLoginPassword) {
        toggleLoginPassword.addEventListener('click', () => {
            const isHidden = loginPassword.type === 'password';
            loginPassword.type = isHidden ? 'text' : 'password';
            toggleLoginPassword.innerHTML = isHidden
                ? '<i data-lucide="eye-off" class="w-5 h-5"></i>'
                : '<i data-lucide="eye" class="w-5 h-5"></i>';
            if (window.lucide) {
                lucide.createIcons();
            }
        });
    }

    if (loginForm && loginSubmit) {
        loginForm.addEventListener('submit', () => {
            loginSubmit.disabled = true;
            const textEl = loginSubmit.querySelector('[data-default-text]');
            if (textEl) {
                textEl.textContent = 'Logowanie...';
            }
        });
    }

    const registerPassword = document.getElementById('register-password');
    const toggleRegisterPassword = document.getElementById('toggle-register-password');
    const registerForm = document.querySelector('form[action="/auth"]');
    const registerSubmit = document.getElementById('register-submit');

    if (registerPassword && toggleRegisterPassword) {
        toggleRegisterPassword.addEventListener('click', () => {
            const isHidden = registerPassword.type === 'password';
            registerPassword.type = isHidden ? 'text' : 'password';
            toggleRegisterPassword.innerHTML = isHidden
                ? '<i data-lucide="eye-off" class="w-5 h-5"></i>'
                : '<i data-lucide="eye" class="w-5 h-5"></i>';
            if (window.lucide) {
                lucide.createIcons();
            }
        });
    }

    if (registerForm && registerSubmit) {
        registerForm.addEventListener('submit', () => {
            registerSubmit.disabled = true;
            const textEl = registerSubmit.querySelector('[data-default-text]');
            if (textEl) {
                textEl.textContent = 'Tworzenie konta...';
            }
        });
    }

    if (window.lucide) {
        lucide.createIcons();
    }
});
