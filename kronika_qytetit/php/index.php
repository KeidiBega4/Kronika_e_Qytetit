<?php
// php will go here later
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Kronika e Qytetit</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" media="(max-width: 768px)" href="../css/mobile.css">
</head>

<body>
    <header class="hero">
        <div class="overlay"></div>

        <!-- NAVBAR -->
        <nav class="navbar" id="navbar">
            <div class="logo">
                <a href="php/index.php">Kronika e Qytetit</a>
            </div>
            
            <input type="checkbox" id="nav-toggle" class="nav-toggle">
            
            <label for="nav-toggle" class="menu-toggle">
                <span></span>
                <span></span>
                <span></span>
            </label>

            <ul class="nav-links">
                <li><a href="index.php">Kreu</a></li>
                <li><a href="lajme.php">Lajme</a></li>
                <li><a href="#rreth-nesh">Rreth Nesh</a></li>
                <li><a href="#kontakt">Kontakt</a></li>
                <li><a href="login.php" class="login-btn">Login</a></li>
            </ul>
        </nav>

        <!-- HERO CONTENT -->
        <div class="hero-content">
            <h1>Kronika e Qytetit</h1>
            <a href="register.php" class="primary-btn">REGJISTROHU</a>
        </div>
    </header>

    <!-- RRETH NESH / ABOUT -->
    <section id="rreth-nesh" class="info-section">
        <div class="container">
            <h2>Rreth Kronika e Qytetit</h2>
            <p class="section-subtitle">
                Portali yt për lajmet më të rëndësishme nga Tirana dhe qytetet e tjera.
            </p>
            <p>
                <strong>Kronika e Qytetit</strong> është një portal informativ i dedikuar
                për lajmet dhe kronikat më të rëndësishme të përditshme. Misioni ynë është
                të sjellim informacion të shpejtë, të verifikuar dhe të besueshëm për publikun.
            </p>
            <p>
                Ne mbulojmë tema nga kronika, politika, komuniteti, kultura dhe zhvillimet
                sociale, duke vendosur në qendër qytetarin dhe historitë e tij.
            </p>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="info-section faq-section">
        <div class="container">
            <h2>Pyetjet më të shpeshta</h2>
            <p class="section-subtitle">Njihuni më mirë me portalin tonë.</p>

            <div class="faq-item">
                <button class="faq-question">
                    <span>❓ Pse duhet të regjistrohem?</span>
                    <span class="faq-toggle">+</span>
                </button>
                <div class="faq-answer">
                    <p>
                        Duke u regjistruar, mund të ruani artikujt e preferuar,
                        të merrni njoftime për lajmet kryesore dhe të personalizoni
                        përvojën tuaj në faqe.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question">
                    <span>❓ Si mund të krijoj një llogari?</span>
                    <span class="faq-toggle">+</span>
                </button>
                <div class="faq-answer">
                    <p>
                        Klikoni tek butoni <strong>“Login”</strong> në krye të faqes dhe
                        zgjidhni opsionin <em>“Regjistrohu”</em>. Plotësoni të dhënat e
                        kërkuara dhe konfirmoni email-in tuaj.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question">
                    <span>❓ Jam i regjistruar, si mund të hyj?</span>
                    <span class="faq-toggle">+</span>
                </button>
                <div class="faq-answer">
                    <p>
                        Klikoni <strong>“Login”</strong>, vendosni email-in dhe
                        fjalëkalimin tuaj dhe më pas shtypni butonin <strong>“Hyr”</strong>.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- FEEDBACK -->
    <section id="feedback" class="info-section feedback-section">
        <div class="container">
            <h2>Feedback nga lexuesit</h2>
            <p class="section-subtitle">
                Na trego çfarë mendon për Kronika e Qytetit – mendimi yt na ndihmon të përmirësohemi.
            </p>

            <form class="feedback-form" action="#" method="post">
                <label for="feedback-text">Mesazhi juaj</label>
                <textarea id="feedback-text" name="feedback" rows="5"
                    placeholder="Shkruaj këtu mendimin ose sugjerimin tënd..."></textarea>

                <button type="submit">Dërgo Feedback</button>
            </form>
        </div>
    </section>

    <!-- KONTAKT -->
    <section id="kontakt" class="info-section contact-section">
        <div class="container">
            <h2>Na kontakto</h2>
            <p class="section-subtitle">
                Për bashkëpunime, informacione shtesë ose raportime nga terreni.
            </p>

            <div class="contact-details">
                <p><strong>Email:</strong> info@kronikaqytetit.com</p>
                <p><strong>Telefon:</strong> 069 734 3140</p>
                <p><strong>Adresa:</strong> Tiranë, Shqipëri</p>
            </div>
        </div>
    </section>

    <!-- NAVBAR SCROLL SCRIPT -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const navbar = document.querySelector('.navbar');

        window.addEventListener('scroll', function () {
            if (window.scrollY > 800) { // after 50px scrolling
                navbar.classList.add('navbar-scrolled');
            } else {
                navbar.classList.remove('navbar-scrolled');
            }
        });
    });
    </script>
</body>
</html>
