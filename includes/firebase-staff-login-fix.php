<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Follow-up fix for the MM5 front-end Firebase login.
 *
 * The original module script used document.currentScript, which is null for
 * JavaScript modules. This initializes the existing login markup explicitly
 * and gives Google sign-in a conventional Google-style treatment.
 */
add_action('wp_footer', function () {
    if (!is_page('login') || !get_page_by_path('dashboard/login')) {
        return;
    }

    $config = surfside_tools_firebase_config();
    ?>
    <style>
        .surfside-firebase-login {
            max-width: 520px;
        }
        .surfside-firebase-login .surfside-google-login {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            min-height: 44px;
            padding: 0 18px;
            background: #fff !important;
            color: #1f1f1f !important;
            border: 1px solid #747775 !important;
            border-radius: 4px !important;
            box-shadow: none !important;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: 500;
            line-height: 1;
            text-decoration: none;
            cursor: pointer;
        }
        .surfside-firebase-login .surfside-google-login:hover,
        .surfside-firebase-login .surfside-google-login:focus {
            background: #f8faff !important;
            border-color: #747775 !important;
            color: #1f1f1f !important;
        }
        .surfside-firebase-login .surfside-google-login:focus-visible {
            outline: 2px solid #0b57d0;
            outline-offset: 2px;
        }
        .surfside-google-mark {
            width: 18px;
            height: 18px;
            flex: 0 0 18px;
        }
        .surfside-firebase-status {
            margin: 0 0 12px;
            color: #4b5872;
        }
        .surfside-firebase-status:empty {
            display: none;
        }
        .surfside-login-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 18px 0;
            color: #667085;
            font-size: 14px;
        }
        .surfside-login-divider::before,
        .surfside-login-divider::after {
            content: '';
            height: 1px;
            background: rgba(7, 27, 58, .16);
            flex: 1;
        }
        .surfside-email-login {
            display: grid;
            gap: 14px;
        }
        .surfside-email-login label {
            display: grid;
            gap: 6px;
            font-weight: 600;
        }
        .surfside-email-login input {
            width: 100%;
            min-height: 44px;
            padding: 9px 11px;
            border: 1px solid rgba(7, 27, 58, .25);
            border-radius: 6px;
            font: inherit;
        }
        .surfside-email-login button {
            width: 100%;
            min-height: 44px;
        }
        .surfside-login-note {
            margin-top: 18px;
            color: #667085;
            font-size: 14px;
        }
    </style>
    <script type="module">
        import { initializeApp, getApps } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js';
        import { getAuth, GoogleAuthProvider, signInWithEmailAndPassword, signInWithPopup } from 'https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js';

        const root = document.querySelector('.surfside-firebase-login');
        if (root && !root.dataset.firebaseInitialized) {
            root.dataset.firebaseInitialized = 'true';

            const config = <?php echo wp_json_encode($config); ?>;
            const app = getApps().length ? getApps()[0] : initializeApp(config);
            const auth = getAuth(app);
            const status = root.querySelector('.surfside-firebase-status');
            const emailForm = root.querySelector('.surfside-email-login');
            const googleButton = root.querySelector('.surfside-google-login');

            googleButton.innerHTML = `
                <svg class="surfside-google-mark" viewBox="0 0 18 18" aria-hidden="true">
                    <path fill="#4285F4" d="M17.64 9.205c0-.638-.057-1.252-.164-1.841H9v3.482h4.844a4.14 4.14 0 0 1-1.797 2.715v2.258h2.909c1.702-1.567 2.684-3.874 2.684-6.614Z"/>
                    <path fill="#34A853" d="M9 18c2.43 0 4.468-.806 5.956-2.181l-2.909-2.258c-.806.54-1.835.859-3.047.859-2.344 0-4.328-1.585-5.037-3.714H.956v2.332A9 9 0 0 0 9 18Z"/>
                    <path fill="#FBBC05" d="M3.963 10.706A5.42 5.42 0 0 1 3.682 9c0-.592.102-1.168.281-1.706V4.962H.956A9 9 0 0 0 0 9c0 1.452.347 2.827.956 4.038l3.007-2.332Z"/>
                    <path fill="#EA4335" d="M9 3.58c1.321 0 2.507.454 3.441 1.346l2.581-2.581C13.464.892 11.426 0 9 0A9 9 0 0 0 .956 4.962l3.007 2.332C4.672 5.165 6.656 3.58 9 3.58Z"/>
                </svg>
                <span>Continue with Google</span>`;

            const showError = (error) => {
                console.error('Surfside Firebase login:', error);
                status.textContent = error?.message || 'Unable to sign in. Please try again.';
            };

            const finish = async (user) => {
                status.textContent = 'Signing you in…';
                const idToken = await user.getIdToken(true);
                const response = await fetch(root.dataset.restUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    credentials: 'same-origin',
                    body: JSON.stringify({idToken}),
                });
                const body = await response.json();
                if (!response.ok) {
                    throw new Error(body.message || 'Unable to start your Surfside Tools session.');
                }
                window.location.assign(root.dataset.redirect);
            };

            emailForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                status.textContent = 'Signing you in…';
                const data = new FormData(emailForm);
                try {
                    const credential = await signInWithEmailAndPassword(
                        auth,
                        String(data.get('email')),
                        String(data.get('password'))
                    );
                    await finish(credential.user);
                } catch (error) {
                    showError(error);
                }
            });

            googleButton.addEventListener('click', async () => {
                status.textContent = 'Opening Google sign in…';
                try {
                    const credential = await signInWithPopup(auth, new GoogleAuthProvider());
                    await finish(credential.user);
                } catch (error) {
                    showError(error);
                }
            });
        }
    </script>
    <?php
}, 100);
