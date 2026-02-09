// ===== DOM elements =====
const videoIdle = document.getElementById('video-idle');
const videoActive = document.getElementById('video-active');
const videoTransition = document.getElementById('video-transition');
const micPanel = document.getElementById('mic-panel');
const micCapsule = document.getElementById('mic-capsule');
const micLabel = document.getElementById('mic-label');
const micHint = document.getElementById('mic-hint');
const subtitles = document.getElementById('subtitles');
const subtitlesText = document.getElementById('subtitles-text');

// ===== Quiz state =====
let questions = [];       // 5 случайных вопросов из API
let currentQuestion = 0;  // индекс текущего вопроса (0-4)
let score = 0;            // кол-во правильных ответов
const videoCache = {};    // кеш blob URL для видео

// ===== Recording state =====
let mediaRecorder = null;
let audioChunks = [];
let isRecording = false;
let silenceTimer = null;
let speechDetected = false;
let analyser = null;
let audioContext = null;
let silenceCheckInterval = null;
let recordingMode = 'code'; // 'code' — код участника, 'answer' — ответ на вопрос
let recordingStartTime = 0;
let persistentStream = null; // постоянный поток микрофона
let dataArray = null;        // массив данных для анализа
const MIN_RECORD_TIME = 1000; // минимум 1 сек до срабатывания детектора тишины

const SILENCE_THRESHOLD = 20;        // порог громкости
const SILENCE_DURATION = 500;        // тишина 0.5 сек для остановки
const MAX_RECORD_TIME = 15000;
const SPEECH_CONFIRM_FRAMES = 3;     // 3 кадра подряд (300мс) выше порога = "речь"

// ===== Subtitles data (intro / ok-code — статичные) =====
const introSubs = [
    [0.0,  0.5,  'Привет!'],
    [0.5,  5.7,  'Я — Marvel бот. Давай проверим, как хорошо ты знаком с продуктами компании Hikvision?'],
    [5.7,  8.0,  'По результатам теста тебя ждут классные призы.'],
    [8.5,  11.5, 'Итак, нужно ответить на пять вопросов.'],
    [11.5, 15.5, 'В каждом из трёх вариантов нужно выбрать один верный и назвать его.'],
    [15.5, 18.0, 'Время на ответ — 15 секунд.'],
    [18.1, 21.3, 'В финале игры ты увидишь количество своих правильных ответов.'],
    [21.4, 22.5, 'Желаю удачи!'],
    [22.6, 28.0, 'Назовите ваш код участника.'],
];

const okCodeSubs = [
    [0.0, 2.0, 'Отлично! Код принят.'],
    [2.0, 5.0, 'Вот первый вопрос...'],
];

// ===== Video preload =====

async function preloadVideo(url) {
    if (videoCache[url]) return videoCache[url];
    try {
        const resp = await fetch(url);
        const blob = await resp.blob();
        videoCache[url] = URL.createObjectURL(blob);
        console.log('Cached:', url);
        return videoCache[url];
    } catch (err) {
        console.warn('Preload failed:', url);
        return url; // fallback на оригинальный URL
    }
}

async function preloadAllVideos() {
    // Статичные видео
    preloadVideo('/videos/ok-code.mp4');

    // Видео вопросов
    questions.forEach(function(q) {
        preloadVideo(q.video);
    });

    // Реакции
    try {
        const resp = await fetch('/quiz/reactions/all');
        const data = await resp.json();
        data.videos.forEach(function(url) {
            preloadVideo(url);
        });
    } catch (err) {
        console.warn('Reactions preload failed');
    }
}

function getCachedUrl(url) {
    return videoCache[url] || url;
}

// ===== Video helpers =====

function playVideo(src, subs, onEnded) {
    videoActive.src = getCachedUrl(src);
    videoActive.muted = false;
    videoActive.loop = false;
    videoActive.load();

    videoActive.oncanplay = function() {
        videoActive.oncanplay = null;
        videoActive.play();
        videoActive.classList.remove('hidden-video');
        if (subs && subs.length) subtitles.classList.add('visible');
    };

    videoActive.ontimeupdate = subs ? function() {
        const t = videoActive.currentTime;
        const subFinal = document.getElementById('sub-final');
        let found = false;
        for (let i = 0; i < subs.length; i++) {
            if (t >= subs[i][0] && t <= subs[i][1]) {
                subtitlesText.innerHTML = subs[i][2];
                subFinal.classList.remove('hidden-sub');
                found = true;
                break;
            }
        }
        if (!found) subFinal.classList.add('hidden-sub');
    } : null;

    videoActive.onended = function() {
        videoActive.ontimeupdate = null;
        videoActive.classList.add('hidden-video');
        videoActive.muted = true;
        hideSubtitles();
        if (onEnded) onEnded();
    };
}

// Простое воспроизведение с одним субтитром на всё видео
function playVideoSimple(src, subtitleText, onEnded) {
    videoActive.src = getCachedUrl(src);
    videoActive.muted = false;
    videoActive.loop = false;
    videoActive.load();

    videoActive.oncanplay = function() {
        videoActive.oncanplay = null;
        videoActive.play();
        videoActive.classList.remove('hidden-video');
        if (subtitleText) showSubtitles(subtitleText);
    };

    videoActive.ontimeupdate = null;

    videoActive.onended = function() {
        videoActive.classList.add('hidden-video');
        videoActive.muted = true;
        hideSubtitles();
        if (onEnded) onEnded();
    };
}

// ===== Start =====

async function startQuiz() {
    document.getElementById('start-screen').style.display = 'none';

    // Инициализируем микрофон сразу (один раз)
    await initMic();

    // Загружаем вопросы из API
    try {
        const resp = await fetch('/quiz/start');
        const data = await resp.json();
        questions = data.questions;
        currentQuestion = 0;
        score = 0;
    } catch (err) {
        console.error('Failed to load questions:', err);
    }

    // Preload все видео в фоне пока играет интро
    preloadAllVideos();

    playIntroOnly();
}

function playIntroOnly() {
    videoActive.src = '/videos/intro.mp4';
    videoActive.muted = false;
    videoActive.loop = false;
    videoActive.load();

    videoActive.oncanplay = function() {
        videoActive.oncanplay = null;
        videoActive.play();
        videoActive.classList.remove('hidden-video');
        subtitles.classList.add('visible');
    };

    const subFinal = document.getElementById('sub-final');
    videoActive.ontimeupdate = function() {
        const t = videoActive.currentTime;
        let found = false;
        for (let i = 0; i < introSubs.length; i++) {
            if (t >= introSubs[i][0] && t <= introSubs[i][1]) {
                subtitlesText.innerHTML = introSubs[i][2];
                subFinal.classList.remove('hidden-sub');
                found = true;
                break;
            }
        }
        if (!found) subFinal.classList.add('hidden-sub');
    };

    videoActive.onended = function() {
        videoActive.ontimeupdate = null;
        videoActive.classList.add('hidden-video');
        videoActive.muted = true;
        subtitlesText.innerHTML = introSubs[introSubs.length - 1][2];
        document.getElementById('sub-final').classList.remove('hidden-sub');
        showMic();
    };
}

// ===== Mic =====

function showMic() {
    recordingMode = 'code';
    micPanel.classList.add('visible');
    micCapsule.classList.remove('recording');
    micLabel.textContent = 'Говорите';
    micHint.textContent = 'Микрофон активен';
    startRecording();
}

function hideMic() {
    micPanel.classList.remove('visible');
}

// Инициализация микрофона один раз
async function initMic() {
    try {
        persistentStream = await navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: false,
                sampleRate: 16000,
                channelCount: 1
            }
        });
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const source = audioContext.createMediaStreamSource(persistentStream);
        analyser = audioContext.createAnalyser();
        analyser.fftSize = 512;
        source.connect(analyser);
        dataArray = new Uint8Array(analyser.frequencyBinCount);
        console.log('Микрофон инициализирован');
    } catch (err) {
        console.error('Mic init error:', err);
    }
}

function startRecording() {
    if (!persistentStream) return;

    audioChunks = [];
    speechDetected = false;
    silenceTimer = null;
    let speechFrames = 0;

    mediaRecorder = new MediaRecorder(persistentStream, {
        mimeType: 'audio/webm;codecs=opus',
        audioBitsPerSecond: 128000
    });

    mediaRecorder.ondataavailable = function(e) {
        if (e.data.size > 0) audioChunks.push(e.data);
    };

    mediaRecorder.onstop = function() {
        clearInterval(silenceCheckInterval);

        if (!speechDetected) {
            console.log('Нет речи — пропуск');
            return;
        }

        const blob = new Blob(audioChunks, { type: 'audio/webm' });
        if (recordingMode === 'answer') {
            sendAnswerToCheck(blob);
        } else {
            sendToWhisper(blob);
        }
    };

    mediaRecorder.start();
    isRecording = true;
    recordingStartTime = Date.now();

    micCapsule.classList.add('recording');
    micLabel.textContent = 'Запись...';
    micHint.textContent = 'Говорите в микрофон';

    silenceCheckInterval = setInterval(function() {
        analyser.getByteFrequencyData(dataArray);
        let sum = 0;
        for (let i = 0; i < dataArray.length; i++) sum += dataArray[i];
        const avg = sum / dataArray.length;

        // Лог уровня (F12 → Console)
        if (avg > 5) console.log('Уровень:', Math.round(avg));

        // Всегда отслеживаем голос
        if (avg > SILENCE_THRESHOLD) {
            speechFrames++;
            if (speechFrames >= SPEECH_CONFIRM_FRAMES) {
                speechDetected = true;
            }
            clearTimeout(silenceTimer);
            silenceTimer = null;
            micLabel.textContent = 'Запись...';
        } else {
            speechFrames = 0;
        }

        // Но останавливать запись можно только после MIN_RECORD_TIME
        const elapsed = Date.now() - recordingStartTime;
        if (elapsed < MIN_RECORD_TIME) return;

        if (speechDetected && avg <= SILENCE_THRESHOLD && !silenceTimer) {
            silenceTimer = setTimeout(function() {
                if (isRecording) stopRecording();
            }, SILENCE_DURATION);
        }
    }, 100);

    setTimeout(function() {
        if (isRecording && speechDetected) stopRecording();
    }, MAX_RECORD_TIME);
}

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        isRecording = false;
        mediaRecorder.stop();
        clearInterval(silenceCheckInterval);
        micCapsule.classList.remove('recording');
        micLabel.textContent = 'Обработка...';
        micHint.textContent = 'Распознаём речь';
    }
}

function toggleRecording() {
    if (isRecording) {
        stopRecording();
    }
}

// ===== Whisper =====

async function sendToWhisper(blob) {
    const formData = new FormData();
    formData.append('audio', blob, 'recording.webm');

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const resp = await fetch('/whisper', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: formData
        });

        const data = await resp.json();

        if (data.text) {
            showSubtitles(data.text);
            hideMic();
            setTimeout(function() {
                hideSubtitles();
                playVideo('/videos/ok-code.mp4', okCodeSubs, function() {
                    playCurrentQuestion();
                });
            }, 2000);
        } else {
            micLabel.textContent = 'Не удалось распознать';
            micHint.textContent = 'Попробуйте ещё раз';
            setTimeout(function() {
                micLabel.textContent = 'Говорите';
                micHint.textContent = 'Микрофон активен';
                startRecording();
            }, 2000);
        }
    } catch (err) {
        console.error('Whisper error:', err);
        micLabel.textContent = 'Ошибка сервера';
        micHint.textContent = 'Попробуйте перезагрузить';
    }
}

// ===== Voice answer check =====

async function sendAnswerToCheck(blob) {
    const q = questions[currentQuestion];
    const formData = new FormData();
    formData.append('audio', blob, 'recording.webm');
    formData.append('option_a', q.options.a);
    formData.append('option_b', q.options.b);
    formData.append('option_c', q.options.c);
    // Подсказка для Whisper — список ожидаемых слов
    formData.append('prompt_hint', 'вариант А, вариант Б, вариант В, ' + q.options.a + ', ' + q.options.b + ', ' + q.options.c);

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const resp = await fetch('/quiz/check-answer', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: formData
        });

        const data = await resp.json();

        hideMic();

        if (data.answer) {
            // Показываем что сказал пользователь
            if (data.transcript) showSubtitles(data.transcript);

            // Находим элемент с этим ответом и "нажимаем"
            const group = document.getElementById('options-group');
            const opt = group.querySelector('[data-answer="' + data.answer + '"]');
            if (opt) {
                setTimeout(function() {
                    selectOption(opt);
                }, 1000);
            }
        } else {
            // Не удалось определить — пусть попробует ещё раз
            showSubtitles(data.transcript || 'Не распознано — попробуйте ещё раз');
            micPanel.classList.add('visible');
            micLabel.textContent = 'Не распознано';
            micHint.textContent = 'Попробуйте ещё раз';
            setTimeout(function() {
                showMicForAnswer();
            }, 2000);
        }
    } catch (err) {
        console.error('Check answer error:', err);
        micLabel.textContent = 'Ошибка сервера';
        micHint.textContent = 'Нажмите вариант вручную';
        hideMic();
    }
}

function showMicForAnswer() {
    recordingMode = 'answer';
    micPanel.classList.add('visible');
    micCapsule.classList.remove('recording');
    micLabel.textContent = 'Назовите ответ';
    micHint.textContent = 'A, B или C';
    startRecording();
}

// ===== Subtitles =====

function showSubtitles(text) {
    subtitlesText.innerHTML = text;
    document.getElementById('sub-final').classList.remove('hidden-sub');
    subtitles.classList.add('visible');
}

function hideSubtitles() {
    subtitles.classList.remove('visible');
}

// ===== Quiz flow =====

function playCurrentQuestion() {
    if (currentQuestion >= questions.length) {
        // Все вопросы пройдены
        showSubtitles('Игра окончена! Правильных ответов: ' + score + ' из ' + questions.length);
        return;
    }

    const q = questions[currentQuestion];

    // Играем видео вопроса с субтитром из БД
    playVideoSimple(q.video, q.subtitle, function() {
        renderOptions(q);
    });
}

function renderOptions(q) {
    const group = document.getElementById('options-group');
    const letters = ['A', 'B', 'C'];
    const keys = ['a', 'b', 'c'];

    group.innerHTML = '';

    keys.forEach(function(key, i) {
        const div = document.createElement('div');
        div.className = 'opt-neon';
        div.dataset.answer = key;
        div.dataset.correct = (key === q.correct) ? 'true' : 'false';
        div.onclick = function() { selectOption(div); };
        div.innerHTML =
            '<div class="check"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>' +
            '<div class="cross"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>' +
            '<div class="letter">' + letters[i] + '</div>' +
            '<div class="sep"></div>' +
            '<div class="text">' + q.options[key] + '</div>';
        group.appendChild(div);
    });

    // Показываем субтитр вопроса + варианты + микрофон сразу
    showSubtitles(q.subtitle);
    document.getElementById('options-panel').classList.add('visible');
    showMicForAnswer();
}

function hideOptions() {
    document.getElementById('options-panel').classList.remove('visible');
}

function selectOption(opt) {
    const group = document.getElementById('options-group');
    if (group.querySelector('.wrong') || group.querySelector('.correct')) return;

    // Останавливаем запись если идёт
    if (isRecording) stopRecording();
    hideMic();

    const isCorrect = opt.dataset.correct === 'true';

    if (isCorrect) {
        opt.classList.add('correct');
        score++;
    } else {
        opt.classList.add('wrong');
        group.querySelector('[data-correct="true"]').classList.add('correct');
    }

    // Через 2 сек — реакция, потом следующий вопрос
    setTimeout(function() {
        hideOptions();
        hideSubtitles();
        playReaction(isCorrect, function() {
            currentQuestion++;
            playCurrentQuestion();
        });
    }, 2000);
}

// ===== Reactions =====

async function playReaction(isCorrect, onDone) {
    const type = isCorrect ? 'correct' : 'wrong';

    try {
        const resp = await fetch('/quiz/reaction/' + type);
        const data = await resp.json();

        playVideoSimple(data.video, data.subtitle, onDone);
    } catch (err) {
        console.error('Reaction error:', err);
        if (onDone) onDone();
    }
}
