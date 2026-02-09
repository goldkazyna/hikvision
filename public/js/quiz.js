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

// ===== VAD recording state =====
let vadInstance = null;
let recordingMode = 'code'; // 'code' — код участника, 'answer' — ответ на вопрос

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
        // cached
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

    // Инициализируем VAD (нейросеть для детекции голоса)
    await initVAD();

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

// ===== Mic (VAD) =====

// Конвертация Float32Array → WAV blob для Whisper
function audioToWav(float32Array, sampleRate) {
    const numChannels = 1;
    const bytesPerSample = 2;
    const blockAlign = numChannels * bytesPerSample;
    const byteRate = sampleRate * blockAlign;
    const dataSize = float32Array.length * bytesPerSample;
    const buffer = new ArrayBuffer(44 + dataSize);
    const view = new DataView(buffer);

    function writeStr(offset, str) {
        for (let i = 0; i < str.length; i++) view.setUint8(offset + i, str.charCodeAt(i));
    }

    writeStr(0, 'RIFF');
    view.setUint32(4, 36 + dataSize, true);
    writeStr(8, 'WAVE');
    writeStr(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, numChannels, true);
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, byteRate, true);
    view.setUint16(32, blockAlign, true);
    view.setUint16(34, 16, true);
    writeStr(36, 'data');
    view.setUint32(40, dataSize, true);

    for (let i = 0; i < float32Array.length; i++) {
        const s = Math.max(-1, Math.min(1, float32Array[i]));
        view.setInt16(44 + i * 2, s * 0x7FFF, true);
    }

    return new Blob([buffer], { type: 'audio/wav' });
}

async function initVAD() {
    try {
        vadInstance = await vad.MicVAD.new({
            positiveSpeechThreshold: 0.8,
            negativeSpeechThreshold: 0.45,
            minSpeechFrames: 6,
            redemptionFrames: 10,
            onSpeechStart: function() {
                micCapsule.classList.add('recording');
                micLabel.textContent = 'Запись...';
                micHint.textContent = 'Слушаю';
            },
            onSpeechEnd: function(audio) {
                micCapsule.classList.remove('recording');
                micLabel.textContent = 'Обработка...';
                micHint.textContent = 'Распознаём речь';

                const wavBlob = audioToWav(audio, 16000);

                if (recordingMode === 'answer') {
                    sendAnswerToCheck(wavBlob);
                } else {
                    sendToWhisper(wavBlob);
                }
            }
        });
    } catch (err) {
        console.error('VAD init error:', err);
    }
}

function showMic() {
    recordingMode = 'code';
    micPanel.classList.add('visible');
    micCapsule.classList.remove('recording');
    micLabel.textContent = 'Говорите';
    micHint.textContent = 'Микрофон активен';
    if (vadInstance) vadInstance.start();
}

function hideMic() {
    micPanel.classList.remove('visible');
    if (vadInstance) vadInstance.pause();
}

function showMicForAnswer() {
    recordingMode = 'answer';
    micPanel.classList.add('visible');
    micCapsule.classList.remove('recording');
    micLabel.textContent = 'Назовите ответ';
    micHint.textContent = 'A, B или C';
    if (vadInstance) vadInstance.start();
}

function toggleRecording() {
    hideMic();
}

// ===== Whisper =====

async function sendToWhisper(blob) {
    const formData = new FormData();
    formData.append('audio', blob, 'recording.wav');

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
                showMic();
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
    formData.append('audio', blob, 'recording.wav');
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

    // Останавливаем VAD
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
