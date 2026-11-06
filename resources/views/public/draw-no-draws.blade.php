<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No Draws Yet - JJ's Firewood Perth</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #2c1810 0%, #5c3a2e 50%, #8b4513 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .container {
            text-align: center;
            padding: 3rem;
            max-width: 600px;
        }
        
        .icon {
            font-size: 8rem;
            margin-bottom: 2rem;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        h1 {
            font-size: 3rem;
            color: #ff6b35;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        p {
            font-size: 1.5rem;
            color: #ffa07a;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #ff6b35, #ff8c42);
            color: white;
            padding: 1.5rem 3rem;
            border-radius: 50px;
            text-decoration: none;
            font-size: 1.3rem;
            font-weight: bold;
            box-shadow: 0 5px 20px rgba(255, 107, 53, 0.4);
            transition: all 0.3s;
        }
        
        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(255, 107, 53, 0.6);
        }
        
        .flames-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
            overflow: hidden;
            pointer-events: none;
        }
        
        .flame {
            position: absolute;
            width: 25px;
            height: 40px;
            background: radial-gradient(circle, #ff6b35, #ff4500);
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            animation: flicker 1.5s ease-in-out infinite;
            opacity: 0.7;
        }
        
        @keyframes flicker {
            0%, 100% { transform: translateY(0) scale(1); opacity: 0.7; }
            50% { transform: translateY(-30px) scale(1.3); opacity: 0.9; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔥🪵</div>
        <h1>No Draws Yet</h1>
        <p>We haven't drawn any giveaway winners yet, but we will soon!</p>
        <p>Every order puts you in the draw for amazing prizes.</p>
        <a href="https://www.jjsfirewoodperth.com.au" class="cta-button">
            Order Firewood Now 🔥
        </a>
    </div>
    
    <div class="flames-container">
        <div class="flame" style="left: 10%; animation-delay: 0s;"></div>
        <div class="flame" style="left: 20%; animation-delay: 0.3s;"></div>
        <div class="flame" style="left: 30%; animation-delay: 0.6s;"></div>
        <div class="flame" style="left: 40%; animation-delay: 0.9s;"></div>
        <div class="flame" style="left: 50%; animation-delay: 1.2s;"></div>
        <div class="flame" style="left: 60%; animation-delay: 1.5s;"></div>
        <div class="flame" style="left: 70%; animation-delay: 1.8s;"></div>
        <div class="flame" style="left: 80%; animation-delay: 2.1s;"></div>
        <div class="flame" style="left: 90%; animation-delay: 2.4s;"></div>
    </div>
</body>
</html>