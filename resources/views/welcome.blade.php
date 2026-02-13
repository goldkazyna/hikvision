<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Hikvision Quiz</title>
    <link rel="stylesheet" href="/css/style.css?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Full-screen video (three layers for crossfade) -->
    <div class="video-bg">
        <video id="video-idle" src="/videos/idle.mp4" autoplay muted loop></video>
        <video id="video-active" class="hidden-video" preload="none" muted></video>
        <video id="video-transition" class="hidden-video" preload="none" muted></video>
    </div>

    <!-- Gradient overlay -->
    <div class="video-overlay"></div>

    <!-- Logos -->
    <div class="header">
        <img src="/img/MARVEL white.png" alt="Marvel">
        <img src="/img/Hikvision Logo-R.png" alt="Hikvision">
    </div>

    <!-- Start screen -->
    <div class="start-screen" id="start-screen">
        <button class="btn-arc-wrap" onclick="startQuiz()">
            <div class="arc-ring"></div>
            <div class="arc-ring"></div>
            <div class="arc-ring"></div>
            <div class="arc-core">
                <div class="icon"></div>
                <span class="label">Старт</span>
            </div>
        </button>
    </div>

    <!-- Timer bar -->
    <div class="timer-bar" id="timerBar">
        <span class="seconds" id="timerSeconds">15</span>
        <div class="bar-wrap">
            <div class="bar-track">
                <div class="bar-fill" id="timerFill"></div>
            </div>
            <span class="bar-label">Время на ответ</span>
        </div>
    </div>

    <!-- Subtitles -->
    <div class="subtitles" id="subtitles">
        <div class="sub-final" id="sub-final">
            <div class="eq-circle">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
            <div class="sub-content">
                <div class="sub-text" id="subtitles-text"></div>
                <div class="underline-track"><div class="runner"></div></div>
            </div>
        </div>
    </div>

    <!-- Mic panel -->
    <div class="mic-panel" id="mic-panel">
        <div class="mic-capsule" id="mic-capsule" onclick="toggleRecording()">
            <div class="mic-dot">
                <div class="ring-anim"></div>
                <svg class="mic-svg" viewBox="0 0 24 24">
                    <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                    <line x1="12" y1="19" x2="12" y2="23"/>
                    <line x1="8" y1="23" x2="16" y2="23"/>
                </svg>
            </div>
            <div class="text-group">
                <span class="main" id="mic-label">Говорите</span>
                <span class="hint" id="mic-hint">Микрофон активен</span>
            </div>
            <div class="wave-mini">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
        </div>
    </div>

    <!-- Options panel -->
    <div class="options-panel" id="options-panel">
        <div class="options-group" id="options-group">
            <div class="opt-neon" data-correct="true" onclick="selectOption(this)">
                <div class="check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div class="cross"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
                <div class="letter">A</div>
                <div class="sep"></div>
                <div class="text">ColorVu</div>
            </div>
            <div class="opt-neon" data-correct="false" onclick="selectOption(this)">
                <div class="check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div class="cross"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
                <div class="letter">B</div>
                <div class="sep"></div>
                <div class="text">DarkFighter</div>
            </div>
            <div class="opt-neon" data-correct="false" onclick="selectOption(this)">
                <div class="check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div class="cross"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
                <div class="letter">C</div>
                <div class="sep"></div>
                <div class="text">WDR</div>
            </div>
        </div>
    </div>

    <script src="/js/ort.js"></script>
    <script src="/js/vad-bundle.min.js"></script>
    <script src="/js/quiz.js?v=12"></script>

</body>
</html>
