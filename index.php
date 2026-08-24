<?php

$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';

putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('SESSION_SECURE_COOKIE=true');
putenv('SESSION_HTTP_ONLY=true');
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
$_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'] = 'false';
$_ENV['SESSION_SECURE_COOKIE'] = $_SERVER['SESSION_SECURE_COOKIE'] = 'true';
$_ENV['SESSION_HTTP_ONLY'] = $_SERVER['SESSION_HTTP_ONLY'] = 'true';

require __DIR__.'/app-hub/public/index.php';

__halt_compiler();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hello There! | Directory Root</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=League+Gothic&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">

    <style>
        /* UH Official Brand Color Palette */
        :root {
            --uh-red: #C8102E;
            --uh-white: #FFFFFF;
            --uh-slate: #54585A;
            --uh-cream: #FFF9D9;
            --uh-gold: #F6BE00;
        }

        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--uh-cream);
            font-family: 'Source Sans 3', sans-serif;
            color: var(--uh-slate);
            text-align: center;
            overflow: hidden; /* Prevents scrollbars from clouds */
        }

        /* --- Cloud System Styles --- */
        #cloud-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1; /* Sits behind the main card */
            pointer-events: none; /* Let clicks pass through container */
        }

        .cloud {
            position: absolute;
            width: 150px;
            height: 50px;
            background: var(--uh-white);
            border-radius: 50px;
            box-shadow: 0 8px 15px rgba(84, 88, 90, 0.05); /* Soft UH Slate shadow */
            cursor: pointer;
            pointer-events: auto; /* Re-enable clicks on the clouds themselves */
            animation: drift linear forwards;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        /* Pure CSS Cloud Shapes */
        .cloud::before, .cloud::after {
            content: '';
            position: absolute;
            background: var(--uh-white);
            border-radius: 50%;
        }

        .cloud::before {
            width: 70px; 
            height: 70px;
            top: -30px; 
            left: 20px;
        }

        .cloud::after {
            width: 50px; 
            height: 50px;
            top: -20px; 
            right: 25px;
        }

        /* Cloud Animations */
        @keyframes drift {
            from { left: -200px; }
            to { left: 110vw; }
        }

        .cloud.pop {
            transform: scale(1.5) translateY(-20px) !important;
            opacity: 0;
        }

        /* --- Main Card Styles --- */
        .card {
            background-color: var(--uh-white);
            padding: 40px 50px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(84, 88, 90, 0.15);
            max-width: 500px;
            width: 90%;
            position: relative;
            z-index: 10; /* Ensures the card stays in front of the clouds */
            border-top: 6px solid var(--uh-red);
            animation: float 6s ease-in-out infinite;
        }

        .sun {
            width: 70px;
            height: 70px;
            background-color: var(--uh-gold);
            border-radius: 50%;
            margin: 0 auto 25px auto;
            position: relative;
            animation: pulse 3s infinite alternate ease-in-out;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .sun:hover {
            transform: scale(1.1);
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 20px rgba(246, 190, 0, 0.4); }
            100% { box-shadow: 0 0 50px rgba(246, 190, 0, 0.8); }
        }

        h1 {
            font-family: 'League Gothic', sans-serif;
            font-size: 3.5rem;
            color: var(--uh-red);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 0 0 15px 0;
            line-height: 1;
        }

        p {
            font-size: 1.15rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .highlight {
            font-weight: 600;
            color: var(--uh-red);
        }

        .footer-note {
            margin-top: 30px;
            font-size: 0.9rem;
            color: #888B8D; /* UH Gray */
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body>

    <div id="cloud-container"></div>

    <div class="card">
        <div class="sun" title="You can click the clouds!"></div>
        
        <h1>Howdy!</h1>
        
        <p>You've wandered into the root directory. There isn't a public website here—just a quiet backstage area hosting a few web apps.</p>
        
        <p>But since you took the time to stop by...</p>
        
        <p class="highlight">Pop a few clouds, drop your shoulders, and have an absolutely fantastic day! Go Coogs!</p>
        
        <div class="footer-note">
            Nothing to click (except the sky), nowhere to go. Just a friendly placeholder.
        </div>
    </div>

    <script>
        const cloudContainer = document.getElementById('cloud-container');

        function createCloud(delay = 0) {
            const cloud = document.createElement('div');
            cloud.classList.add('cloud');
            
            // Randomize size, height, and speed for natural variance
            const scale = Math.random() * 0.6 + 0.5; // Scales between 0.5x and 1.1x
            const topPosition = Math.random() * 85; // Keeps them mostly on screen vertically
            const duration = Math.random() * 25 + 20; // Takes 20-45 seconds to cross the screen
            
            cloud.style.top = topPosition + '%';
            cloud.style.transform = `scale(${scale})`;
            cloud.style.animationDuration = duration + 's';
            
            // Allow them to start off-screen or with a staggered delay
            if (delay > 0) {
                cloud.style.animationDelay = delay + 's';
            }

            // The Interaction: Click to Pop
            cloud.addEventListener('click', () => {
                cloud.classList.add('pop');
                
                // Wait for the pop CSS transition to finish, then remove and replace
                setTimeout(() => {
                    cloud.remove();
                    createCloud(); 
                }, 300);
            });

            // If a cloud makes it all the way across without being clicked, respawn it
            cloud.addEventListener('animationend', () => {
                cloud.remove();
                createCloud();
            });

            cloudContainer.appendChild(cloud);
        }

        // Initialize 6 clouds with staggered start times so they don't clump together
        for (let i = 0; i < 6; i++) {
            // Negative delay acts like they've already been traveling for a while
            createCloud(Math.random() * -30);
        }
    </script>

</body>
</html>