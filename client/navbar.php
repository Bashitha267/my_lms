<?php
// Get current page name for active tab highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
        .navbar.navbar-dark .navbar-nav .nav-link,
        .navbar .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500 !important;
            margin: 0 0.3rem;
            padding: 0.4rem 0.8rem !important;
            border-radius: 5px;
            position: relative;
            display: block;
            visibility: visible !important;
            opacity: 1 !important;
            font-size: 0.95rem;
        }

        .navbar.navbar-dark .navbar-nav .nav-link i,
        .navbar .navbar-nav .nav-link i {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            margin-right: 0.25rem;
        }

        .navbar.navbar-dark .navbar-nav .nav-link.active,
        .navbar .navbar-nav .nav-link.active {
            background-color: white !important;
            color: #dc2626 !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .navbar.navbar-dark .navbar-nav .nav-link.active i,
        .navbar .navbar-nav .nav-link.active i,
        .navbar .navbar-nav .nav-link.active span {
            color: #dc2626 !important;
        }

        .navbar-brand {
            color: white !important;
            visibility: visible !important;
        }

        .navbar-brand i {
            display: inline-block !important;
            visibility: visible !important;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 1%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .navbar-buttons .btn {
            visibility: visible !important;
            opacity: 1 !important;
            display: inline-flex !important;
        }

        .navbar-buttons .btn i {
            display: inline-block !important;
            visibility: visible !important;
            margin-right: 0.25rem;
        }

        .navbar-buttons .btn span {
            display: inline-block !important;
            visibility: visible !important;
        }

        @media (max-width: 991px) {
            .navbar-nav {
                margin-top: 1rem;
            }
            .navbar-nav .nav-link {
                margin: 0.25rem 0;
            }
            .navbar-buttons {
                margin-top: 1rem;
                flex-direction: column;
                width: 100%;
            }
            .navbar-buttons a {
                width: 100%;
                margin: 0.5rem 0 !important;
            }
        }
    </style>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); padding: 0.5rem 0; z-index: 1000;">
        <div class="container">
            <a class="navbar-brand" href="index.php" style="font-size: 1.5rem; font-weight: bold; color: white !important; display: inline-flex !important; align-items: center !important;">
                <i class="fas fa-graduation-cap me-2" style="display: inline-block !important; visibility: visible !important;"></i><span style="display: inline-block !important; visibility: visible !important;">LMS</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border: 2px solid white;">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="index.php" style="display: flex !important; align-items: center !important;">
                            <i class="fas fa-home me-1" style="display: inline-block !important; visibility: visible !important;"></i><span style="display: inline-block !important; visibility: visible !important;">Home</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'staff.php') ? 'active' : ''; ?>" href="staff.php" style="display: flex !important; align-items: center !important;">
                            <i class="fas fa-users me-1" style="display: inline-block !important; visibility: visible !important;"></i><span style="display: inline-block !important; visibility: visible !important;">Staff</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'subjects.php') ? 'active' : ''; ?>" href="subjects.php" style="display: flex !important; align-items: center !important;">
                            <i class="fas fa-book me-1" style="display: inline-block !important; visibility: visible !important;"></i><span style="display: inline-block !important; visibility: visible !important;">Subjects</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'courses.php') ? 'active' : ''; ?>" href="courses.php" style="display: flex !important; align-items: center !important;">
                            <i class="fas fa-laptop-code me-1" style="display: inline-block !important; visibility: visible !important;"></i><span style="display: inline-block !important; visibility: visible !important;">Courses</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'examresults.php') ? 'active' : ''; ?>" href="examresults.php" style="display: flex !important; align-items: center !important;">
                            <i class="fas fa-clipboard-list me-1" style="display: inline-block !important; visibility: visible !important;"></i><span style="display: inline-block !important; visibility: visible !important;">Exam Results</span>
                        </a>
                    </li>
                </ul>
                <div class="d-flex navbar-buttons md:mr-4 items-center">
                    <!-- Language Toggle -->
                    <div class="btn-group me-3" role="group" style="border: 1px solid rgba(255,255,255,0.8); border-radius: 20px; padding: 0.1rem;">
                        <button type="button" class="btn btn-sm language-btn lang-en" data-lang="en" style="padding: 0.25rem 0.75rem; background: transparent; color: white; font-weight: 600; border: none; border-radius: 20px 0 0 20px; font-size: 0.85rem;">
                            <i class="fas fa-globe-americas me-1"></i>ENG
                        </button>
                        <button type="button" class="btn btn-sm language-btn lang-si" data-lang="si" style="padding: 0.25rem 0.75rem; background: transparent; color: white; font-weight: 600; border: none; border-left: 1px solid rgba(255,255,255,0.3); border-radius: 0 20px 20px 0; font-size: 0.85rem;">
                            සිංහල
                        </button>
                    </div>

                    <a href="../login.php" class="btn" style="padding: 0.35rem 1.2rem; font-size: 0.9rem; font-weight: 600; border-radius: 25px; margin-left: 0.5rem; background: transparent !important; border: 2px solid white !important; color: white !important; display: inline-flex !important; align-items: center !important; visibility: visible !important; opacity: 1 !important;">
                        <i class="fas fa-sign-in-alt me-1" style="display: inline-block !important; visibility: visible !important;"></i><span class="lang-text" data-en="Login" data-si="ඇතුල් වන්න">Login</span>
                    </a>
                    <a href="../register.php" class="btn" style="padding: 0.35rem 1.2rem; font-size: 0.9rem; font-weight: 600; border-radius: 25px; margin-left: 0.5rem; background: white !important; border: 2px solid white !important; color: #dc2626 !important; display: inline-flex !important; align-items: center !important; visibility: visible !important; opacity: 1 !important;">
                        <i class="fas fa-user-plus me-1" style="display: inline-block !important; visibility: visible !important; color: #dc2626 !important;"></i><span class="lang-text" data-en="Sign Up" data-si="ලියාපදිංචි වන්න">Sign Up</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <script>
        // Language switching
        document.addEventListener('DOMContentLoaded', function() {
            const savedLanguage = localStorage.getItem('lms-language') || 'en';
            setLanguage(savedLanguage);

            document.querySelectorAll('.language-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    setLanguage(this.dataset.lang);
                });
            });

            // Add click handler to navigation links to show auth popup
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    // Allow home/index to work, block others
                    if (href !== 'index.php' && !href.includes('#')) {
                        e.preventDefault();
                        showAuthPopup();
                    }
                });
            });
        });

        function setLanguage(lang) {
            localStorage.setItem('lms-language', lang);
            
            // Update active button
            document.querySelectorAll('.language-btn').forEach(btn => {
                btn.style.background = btn.dataset.lang === lang ? 'rgba(255,255,255,0.2)' : 'transparent';
            });

            // Update all language texts
            document.querySelectorAll('.lang-text').forEach(element => {
                element.textContent = element.getAttribute(`data-${lang}`);
            });

            // Dispatch custom event for pages to listen
            window.dispatchEvent(new CustomEvent('languageChanged', { detail: { language: lang } }));
        }

        // Show authentication popup
        function showAuthPopup() {
            // Create modal if it doesn't exist
            let modal = document.getElementById('authRequiredModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'authRequiredModal';
                modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
                modal.innerHTML = `
                    <div class="bg-white rounded-lg shadow-2xl p-8 max-w-md w-full">
                        <div class="text-center">
                            <i class="fas fa-lock text-red-600 text-5xl mb-4"></i>
                            <h3 class="text-2xl font-bold text-gray-900 mb-3">Authentication Required</h3>
                            <p class="text-gray-600 mb-6">Please log in or register to access this feature.</p>
                        </div>
                        <div class="flex flex-col space-y-3">
                            <a href="../login.php" 
                               class="w-full bg-red-600 text-white py-3 px-6 rounded-lg hover:bg-red-700 text-center font-semibold transition flex items-center justify-center">
                                <i class="fas fa-sign-in-alt mr-2"></i>Login
                            </a>
                            <a href="../register.php 
                               class="w-full bg-gray-600 text-white py-3 px-6 rounded-lg hover:bg-gray-700 text-center font-semibold transition flex items-center justify-center">
                                <i class="fas fa-user-plus mr-2"></i>Register
                            </a>
                            <button onclick="closeAuthPopup()" 
                                    class="w-full text-gray-600 hover:text-gray-800 py-2 transition">
                                Cancel
                            </button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                
                // Close on outside click
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeAuthPopup();
                    }
                });
            }
            modal.style.display = 'flex';
        }

        // Close authentication popup
        function closeAuthPopup() {
            const modal = document.getElementById('authRequiredModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
    </script>
