const safeJsonParse = (value, fallback) => {
    try {
        return value ? JSON.parse(value) : fallback;
    } catch (error) {
        console.warn('JSON parse error', error);
        return fallback;
    }
};

// Global error logger for easier debugging in the browser console
window.addEventListener('error', (evt) => {
    console.error('EMO_JS_ERROR:', evt.message, evt.filename, evt.lineno, evt.error);
});

const initPatientDashboard = () => {
    console.log('patient-dashboard.js: init');
    try {
        const container = document.querySelector('.dashboard-grid--patient');
        if (!(container instanceof HTMLElement)) {
            return;
        }

    const userId = Number(container.dataset.userId || '0');
    const _rawEmotionOptions = container.dataset.emotionOptions ?? null;
    let emotionOptions = [];
    if (_rawEmotionOptions) {
        emotionOptions = safeJsonParse(_rawEmotionOptions, []);
    } else if (typeof window !== 'undefined' && Array.isArray(window.__emotionOptions)) {
        emotionOptions = window.__emotionOptions;
    }
    let chatThreadId = container.dataset.chatThread || null;
    let chatPollingHandle = null;
    let chatLastMessageId = 0;

    const moodForm = document.querySelector('#mood-form');
    const categoryInput = moodForm?.querySelector('input[name="emotion_category"]');
    const subcategoryInput = moodForm?.querySelector('input[name="emotion_subcategory"]');
    const intensitySlider = document.querySelector('#emotion-intensity');
    const intensityValue = document.querySelector('#emotion-intensity-value');
    const treeImage = document.querySelector('#tree-stage-image');
    const treeMessage = document.querySelector('#tree-stage-message');
    const habitList = document.querySelector('#habit-list');
    const refreshHabitsButton = document.querySelector('#refresh-habits');
    const moodChartCanvas = document.querySelector('#mood-chart');
    const timelineList = document.querySelector('#emotion-timeline');
    const historyRefreshButton = document.querySelector('#reload-history');
    const chatWindow = document.querySelector('#chat-window');
    const chatHeader = document.querySelector('#chat-header');
    const chatMessages = document.querySelector('#chat-messages');
    const chatForm = document.querySelector('#chat-form');
    const chatInput = chatForm?.querySelector('input[name="message"]');
    const chatRefreshButton = document.querySelector('#chat-refresh');

    const moodHistory = safeJsonParse(moodChartCanvas?.dataset.moodHistory, []);
    let moodChart = null;

    const initMoodChart = () => {
        if (!moodChartCanvas || !window.Chart) {
            return;
        }

        const labels = moodHistory.map(point => point.date).reverse();
        const levelDataset = moodHistory.map(point => point.level).reverse();
        const intensityDataset = moodHistory.map(point => point.intensity).reverse();

        moodChart = new Chart(moodChartCanvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Poziom nastroju',
                        data: levelDataset,
                        tension: 0.4,
                        borderColor: '#2a9d8f',
                        backgroundColor: 'rgba(42, 157, 143, 0.2)',
                        fill: true,
                        pointRadius: 4,
                        yAxisID: 'mood',
                    },
                    {
                        label: 'Intensywność emocji',
                        data: intensityDataset,
                        tension: 0.4,
                        borderColor: '#f4a261',
                        backgroundColor: 'rgba(244, 162, 97, 0.15)',
                        fill: true,
                        pointRadius: 3,
                        yAxisID: 'intensity',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    mood: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        min: 0,
                        max: 5,
                        title: {
                            display: true,
                            text: 'Nastrój (1-5)',
                        },
                    },
                    intensity: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        min: 0,
                        max: 10,
                        grid: {
                            drawOnChartArea: false,
                        },
                        title: {
                            display: true,
                            text: 'Intensywność (1-10)',
                        },
                    },
                },
            },
        });
    };

    const appendMoodPoint = (point) => {
        if (!moodChart) {
            return;
        }
        moodChart.data.labels.push(point.date);
        moodChart.data.datasets[0].data.push(point.level);
        moodChart.data.datasets[1].data.push(point.intensity || 0);

        if (moodChart.data.labels.length > 60) {
            moodChart.data.labels.shift();
            moodChart.data.datasets.forEach(dataset => dataset.data.shift());
        }

        moodChart.update();
    };

    const prependTimelineItem = (entry) => {
        if (!timelineList) {
            return;
        }

        const item = document.createElement('li');
        item.className = 'timeline__item';

        const date = document.createElement('div');
        date.className = 'timeline__date';
        date.textContent = entry.date;

        const content = document.createElement('div');
        content.className = 'timeline__content';

        const strong = document.createElement('strong');
        strong.textContent = entry.category?.name ?? entry.category ?? 'Emocja';
        content.appendChild(strong);

        if (entry.subcategory?.name) {
            const sub = document.createElement('span');
            sub.textContent = ` • ${entry.subcategory.name}`;
            content.appendChild(sub);
        }

        const intensity = document.createElement('span');
        intensity.className = 'timeline__intensity';
        intensity.textContent = `${entry.intensity ?? 0}/10`;
        content.appendChild(intensity);

        if (entry.note) {
            const note = document.createElement('p');
            note.textContent = entry.note;
            content.appendChild(note);
        }

        item.append(date, content);
        timelineList.prepend(item);

        while (timelineList.children.length > 10) {
            timelineList.lastElementChild?.remove();
        }
    };

    const updateTree = async () => {
        try {
            const response = await fetch('/patient/tree', { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('HTTP error');
            const data = await response.json();
            if (treeImage) {
                treeImage.src = `/assets/tree-stage-${data.stage}.svg`;
            }
            if (treeMessage) {
                treeMessage.textContent = data.message;
            }
        } catch (error) {
            console.warn('Nie udało się zaktualizować drzewka.', error);
        }
    };

    const refreshHabits = async () => {
        if (!habitList) return;
        try {
            const response = await fetch('/api/patient/habits', { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('HTTP error');
            const data = await response.json();
            habitList.innerHTML = '';
            data.habits.forEach(habit => {
                const li = document.createElement('li');
                li.className = 'habit-item';
                li.dataset.habitId = habit.id;

                const info = document.createElement('div');
                const title = document.createElement('h3');
                title.textContent = habit.name;
                const desc = document.createElement('p');
                desc.textContent = habit.description ?? '';
                info.append(title, desc);

                const progressWrapper = document.createElement('div');
                progressWrapper.className = 'habit-progress';
                const progressText = document.createElement('span');
                progressText.textContent = `${habit.completed_count}/${habit.frequency_goal} w ostatnich 14 dniach`;
                const button = document.createElement('button');
                button.className = 'button button--secondary';
                button.dataset.habitLog = '1';
                button.type = 'button';
                button.textContent = 'Zapisz postęp';
                progressWrapper.append(progressText, button);

                li.append(info, progressWrapper);
                habitList.append(li);
            });
        } catch (error) {
            console.warn('Nie udało się pobrać nawyków.', error);
        }
    };

    const loadMoodHistory = async () => {
        try {
            const response = await fetch('/api/patient/moods', { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('HTTP error');
            const data = await response.json();
            const history = data.timeline ?? [];

            if (moodChart) {
                moodChart.data.labels = history.map(point => point.date).reverse();
                moodChart.data.datasets[0].data = history.map(point => point.level).reverse();
                moodChart.data.datasets[1].data = history.map(point => point.intensity ?? 0).reverse();
                moodChart.update();
            }

            if (timelineList) {
                timelineList.innerHTML = '';
                history.slice().reverse().forEach(prependTimelineItem);
            }
        } catch (error) {
            console.warn('Nie udało się odświeżyć historii nastroju.', error);
        }
    };

    const buildEmotionWheel = () => {
        const svg = document.querySelector('#emotion-wheel-svg');
        const segmentsGroup = document.querySelector('#emotion-wheel-segments');
        const centerText = document.querySelector('#emotion-wheel-center-text');
        const subcategoriesWrapper = document.querySelector('#emotion-subcategories');
        const subcategoriesList = document.querySelector('#emotion-subcategories-list');
        const subcategoriesTitle = document.querySelector('#emotion-subcategories-title');

        if (!svg || !segmentsGroup || !centerText || !subcategoriesWrapper || !subcategoriesList) {
            return;
        }

        if (!categoryInput || !subcategoryInput) {
            return;
        }

        if (emotionOptions.length === 0) {
            return;
        }

        const centerX = 200;
        const centerY = 200;
        const innerRadius = 60;
        const outerRadius = 180;
        const angleStep = (2 * Math.PI) / emotionOptions.length;
        let currentActiveCategory = null;

        const selectCategory = (category) => {
            if (currentActiveCategory) {
                const prevPath = segmentsGroup.querySelector(`[data-category-slug="${currentActiveCategory.slug}"]`);
                if (prevPath) {
                    prevPath.classList.remove('is-active');
                }
            }

            categoryInput.value = category.slug;
            subcategoryInput.value = '';
            centerText.textContent = category.name;
            centerText.setAttribute('fill', category.accentColor || '#2a9d8f');
            console.log('EMO_DEBUG: category selected ->', category.slug);

            const path = segmentsGroup.querySelector(`[data-category-slug="${category.slug}"]`);
            if (path) {
                path.classList.add('is-active');
            }

            currentActiveCategory = category;

            subcategoriesList.innerHTML = '';
            if (category.subcategories.length === 0) {
                subcategoriesWrapper.classList.add('is-hidden');
                return;
            }

            subcategoriesTitle.textContent = `Rozszerzenie: ${category.name}`;
            subcategoriesWrapper.classList.remove('is-hidden');
            category.subcategories.forEach(sub => {
                const li = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = sub.name;
                button.addEventListener('click', () => {
                    // Ensure category is still set when selecting subcategory
                    if (currentActiveCategory && categoryInput) {
                        categoryInput.value = currentActiveCategory.slug;
                    }
                    subcategoryInput.value = sub.slug;
                    subcategoriesList.querySelectorAll('button').forEach(btn => btn.classList.remove('is-active'));
                    button.classList.add('is-active');
                    console.log('EMO_DEBUG: subcategory selected ->', currentActiveCategory?.slug, '/', sub.slug);
                });
                li.append(button);
                subcategoriesList.append(li);
            });
        };

        emotionOptions.forEach((category, index) => {
            const startAngle = index * angleStep - Math.PI / 2;
            const endAngle = (index + 1) * angleStep - Math.PI / 2;

            const x1 = centerX + innerRadius * Math.cos(startAngle);
            const y1 = centerY + innerRadius * Math.sin(startAngle);
            const x2 = centerX + outerRadius * Math.cos(startAngle);
            const y2 = centerY + outerRadius * Math.sin(startAngle);
            const x3 = centerX + outerRadius * Math.cos(endAngle);
            const y3 = centerY + outerRadius * Math.sin(endAngle);
            const x4 = centerX + innerRadius * Math.cos(endAngle);
            const y4 = centerY + innerRadius * Math.sin(endAngle);

            const largeArc = endAngle - startAngle > Math.PI ? 1 : 0;

            const pathData = [
                `M ${x1} ${y1}`,
                `L ${x2} ${y2}`,
                `A ${outerRadius} ${outerRadius} 0 ${largeArc} 1 ${x3} ${y3}`,
                `L ${x4} ${y4}`,
                `A ${innerRadius} ${innerRadius} 0 ${largeArc} 0 ${x1} ${y1}`,
                'Z'
            ].join(' ');

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', pathData);
            path.setAttribute('fill', category.accentColor || '#2a9d8f');
            path.setAttribute('data-category-slug', category.slug);
            path.setAttribute('data-category-name', category.name);
            path.style.cursor = 'pointer';

            path.addEventListener('click', () => selectCategory(category));
            path.addEventListener('mouseenter', () => {
                if (!path.classList.contains('is-active')) {
                    path.style.opacity = '0.85';
                }
            });
            path.addEventListener('mouseleave', () => {
                if (!path.classList.contains('is-active')) {
                    path.style.opacity = '1';
                }
            });

            const midAngle = (startAngle + endAngle) / 2;
            const labelRadius = (innerRadius + outerRadius) / 2;
            const labelX = centerX + labelRadius * Math.cos(midAngle);
            const labelY = centerY + labelRadius * Math.sin(midAngle);

            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', labelX);
            text.setAttribute('y', labelY);
            text.setAttribute('text-anchor', 'middle');
            text.setAttribute('dominant-baseline', 'middle');
            text.textContent = category.name;
            text.style.pointerEvents = 'none';
            text.style.fontSize = '13px';
            text.style.fontWeight = '600';
            text.style.fill = 'white';
            text.style.textShadow = '0 1px 2px rgba(0,0,0,0.3)';

            segmentsGroup.appendChild(path);
            segmentsGroup.appendChild(text);
        });
    };

    // Centered modal helper: shows title and supportive messages, then calls cb
    const showCenterModal = (titleText, subtitleText, messages = [], duration = 1500, cb = null) => {
        // remove existing
        const existing = document.querySelector('.center-modal__backdrop');
        if (existing) existing.remove();

        const backdrop = document.createElement('div');
        backdrop.className = 'center-modal__backdrop';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');

        const panel = document.createElement('div');
        panel.className = 'center-modal__content';

        const header = document.createElement('div');
        header.className = 'center-modal__header';

        const title = document.createElement('div');
        title.className = 'center-modal__title';
        title.textContent = titleText;

        header.appendChild(title);
        panel.appendChild(header);

        if (subtitleText) {
            const sub = document.createElement('div');
            sub.className = 'center-modal__subtitle';
            sub.textContent = subtitleText;
            panel.appendChild(sub);
        }

        if (Array.isArray(messages) && messages.length > 0) {
            const list = document.createElement('div');
            list.className = 'center-modal__messages';
            messages.slice(0,5).forEach(msg => {
                const p = document.createElement('p');
                p.innerHTML = msg;
                list.appendChild(p);
            });
            panel.appendChild(list);
        }

        const actions = document.createElement('div');
        actions.className = 'center-modal__actions';
        const ok = document.createElement('button');
        ok.className = 'button button--primary button--modal';
        ok.textContent = 'OK';
        ok.addEventListener('click', () => {
            backdrop.remove();
            if (typeof cb === 'function') cb();
        });
        actions.appendChild(ok);
        panel.appendChild(actions);

        backdrop.appendChild(panel);
        document.body.appendChild(backdrop);

        // do not auto-close; require explicit user action (OK button)
        // focus OK for keyboard users
        requestAnimationFrame(() => ok.focus());
    };

    // Inline message helper (non-blocking) - inserts message at top of target element
    const showInlineMessage = (targetElem, message, type = 'error', duration = 4000) => {
        try {
            if (!targetElem) return null;
            const existing = targetElem.querySelector('.inline-message');
            if (existing) existing.remove();
            const msg = document.createElement('div');
            msg.className = 'inline-message ' + (type === 'success' ? 'inline-message--success' : 'inline-message--error');
            msg.textContent = message;
            targetElem.insertBefore(msg, targetElem.firstChild);
            if (duration > 0) {
                setTimeout(() => { msg.remove(); }, duration);
            }
            return msg;
        } catch (e) {
            console.warn('showInlineMessage failed', e);
            return null;
        }
    };

    // Centered prompt: returns a Promise that resolves to the input string or null if cancelled
    const showCenterPrompt = (titleText, placeholder = '', defaultValue = '', opts = {}) => {
        return new Promise((resolve) => {
            const existing = document.querySelector('.center-modal__backdrop');
            if (existing) existing.remove();

            const backdrop = document.createElement('div');
            backdrop.className = 'center-modal__backdrop';
            backdrop.setAttribute('role', 'dialog');
            backdrop.setAttribute('aria-modal', 'true');

            const panel = document.createElement('div');
            panel.className = 'center-modal__content';

            const header = document.createElement('div');
            header.className = 'center-modal__header';
            const title = document.createElement('div');
            title.className = 'center-modal__title';
            title.textContent = titleText;
            header.appendChild(title);
            panel.appendChild(header);

            const inputWrapper = document.createElement('div');
            inputWrapper.style.marginTop = '12px';
            const input = document.createElement('input');
            input.type = opts.type || 'text';
            if (opts.min !== undefined) input.min = String(opts.min);
            if (opts.max !== undefined) input.max = String(opts.max);
            input.placeholder = placeholder;
            input.value = defaultValue;
            input.className = 'form-field__input';
            inputWrapper.appendChild(input);
            panel.appendChild(inputWrapper);

            const actions = document.createElement('div');
            actions.className = 'center-modal__actions';
            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'button button--ghost button--modal';
            cancelBtn.textContent = 'Anuluj';
            const okBtn = document.createElement('button');
            okBtn.className = 'button button--primary button--modal';
            okBtn.textContent = 'OK';

            cancelBtn.addEventListener('click', () => {
                backdrop.remove();
                resolve(null);
            });

            okBtn.addEventListener('click', () => {
                const val = input.value ?? '';
                backdrop.remove();
                resolve(val.trim() === '' ? '' : val);
            });

            actions.appendChild(okBtn);
            actions.appendChild(cancelBtn);
            panel.appendChild(actions);

            backdrop.appendChild(panel);
            document.body.appendChild(backdrop);

            // keyboard handling
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); okBtn.click(); }
                if (e.key === 'Escape') { e.preventDefault(); cancelBtn.click(); }
            });

            requestAnimationFrame(() => input.focus());
        });
    };

    const startChatPolling = () => {
        if (chatPollingHandle) {
            clearInterval(chatPollingHandle);
        }
        chatPollingHandle = window.setInterval(() => fetchChatMessages(false), 5000);
    };

    const renderChatMessages = (messages = []) => {
        if (!chatMessages) return;
        messages.forEach(message => {
            const item = document.createElement('div');
            item.className = 'chat-message';
            if (message.sender_user_id === userId) {
                item.classList.add('chat-message--mine');
            }
            const body = document.createElement('p');
            body.textContent = message.body;
            const meta = document.createElement('span');
            const time = new Date(message.created_at);
            meta.textContent = time.toLocaleString();
            item.append(body, meta);
            chatMessages.append(item);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            chatLastMessageId = Math.max(chatLastMessageId, message.id);
        });
    };

    const fetchChatMessages = async (initial = false) => {
        if (!chatWindow) return;
        try {
            const params = new URLSearchParams();
            if (!initial && chatLastMessageId > 0) {
                params.set('after_id', String(chatLastMessageId));
            }
            const response = await fetch(`/api/chat/messages?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error('HTTP error');
            const data = await response.json();
            if (!data || data.status !== 'ok') return;

            chatThreadId = data.thread_id ?? chatThreadId;

            if (initial) {
                chatMessages.innerHTML = '';
                chatLastMessageId = 0;
            }

            renderChatMessages(data.messages ?? []);

            if (data.participants?.psychologist && chatHeader) {
                chatHeader.textContent = `Rozmowa z ${data.participants.psychologist.name}`;
            }
            chatWindow?.classList.add('is-ready');
        } catch (error) {
            console.warn('Nie udało się pobrać wiadomości.', error);
        }
    };

    chatForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!chatInput || chatInput.value.trim() === '') {
            return;
        }

        const formData = new FormData(chatForm);
        try {
            const response = await fetch('/api/chat/messages', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData,
            });
            const data = await response.json();
            if (response.ok && data.message) {
                renderChatMessages([data.message]);
                chatInput.value = '';
            } else {
                showInlineMessage(chatForm?.parentElement || chatWindow, data.error ?? 'Nie udało się wysłać wiadomości.', 'error');
            }
        } catch (error) {
            console.warn('Błąd wysyłania wiadomości', error);
        }
    });

    chatRefreshButton?.addEventListener('click', (event) => {
        event.preventDefault();
        fetchChatMessages(true);
    });

    historyRefreshButton?.addEventListener('click', (event) => {
        event.preventDefault();
        loadMoodHistory();
    });

    habitList?.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (!target.dataset.habitLog) return;

        const listItem = target.closest('.habit-item');
        if (!listItem) return;

        const habitId = listItem.dataset.habitId;
        const moodLevel = await showCenterPrompt('Jak oceniasz swój nastrój po wykonaniu nawyku? (1-5)', 'Wprowadź liczbę 1-5', '', { type: 'number', min: 1, max: 5 });

        const formData = new FormData();
        formData.append('habit_id', habitId);
        formData.append('completed', '1');
        if (moodLevel) {
            formData.append('mood_level', moodLevel);
        }

        try {
            const response = await fetch('/patient/habits', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData,
            });
            const data = await response.json();
            if (response.ok) {
                await refreshHabits();
                await updateTree();
            } else {
                showInlineMessage(listItem, data.error ?? 'Nie udało się zapisać postępu.', 'error');
            }
        } catch (error) {
            showInlineMessage(listItem, 'Wystąpił błąd podczas zapisu postępu.', 'error');
        }
    });

    refreshHabitsButton?.addEventListener('click', (event) => {
        event.preventDefault();
        refreshHabits();
    });

    const addHabitBtn = document.querySelector('#add-habit-btn');
    const addHabitForm = document.querySelector('#add-habit-form');
    const cancelHabitBtn = document.querySelector('#cancel-habit-btn');

    addHabitBtn?.addEventListener('click', () => {
        addHabitForm?.classList.remove('is-hidden');
        addHabitBtn.classList.add('is-hidden');
    });

    cancelHabitBtn?.addEventListener('click', () => {
        addHabitForm?.classList.add('is-hidden');
        addHabitBtn?.classList.remove('is-hidden');
        addHabitForm?.reset();
    });

    addHabitForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const formData = new FormData(addHabitForm);
        try {
            const response = await fetch('/patient/habits/create', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            });
            const data = await response.json();
                if (response.ok) {
                await refreshHabits();
                addHabitForm?.classList.add('is-hidden');
                addHabitBtn?.classList.remove('is-hidden');
                addHabitForm?.reset();
            } else {
                showInlineMessage(addHabitForm || habitList, data.error ?? 'Nie udało się utworzyć nawyku.', 'error');
            }
        } catch (error) {
            showInlineMessage(addHabitForm || habitList, 'Wystąpił błąd podczas tworzenia nawyku.', 'error');
        }
    });

    intensitySlider?.addEventListener('input', () => {
        intensityValue.textContent = `${intensitySlider.value}/10`;
    });

    moodForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(moodForm);

        const categoryValue = categoryInput?.value?.trim();
        console.log('EMO_DEBUG: submit categoryValue=', categoryValue, 'subcategoryValue=', subcategoryInput?.value);
        if (!categoryValue) {
            showInlineMessage(moodForm, 'Wybierz emocję z koła.', 'error');
            return;
        }

        try {
            const response = await fetch('/patient/moods', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData,
            });
            const data = await response.json();
            if (response.ok) {
                appendMoodPoint({
                    date: data.mood.date,
                    level: data.mood.level,
                    intensity: data.mood.intensity ?? Number(intensitySlider?.value || '5'),
                });
                prependTimelineItem({
                    date: data.mood.date,
                    category: data.mood.category,
                    subcategory: data.mood.subcategory,
                    intensity: data.mood.intensity,
                    note: data.mood.note,
                });
                await updateTree();
                moodForm.reset();
                intensitySlider.value = '5';
                intensitySlider.dispatchEvent(new Event('input'));
                categoryInput.value = '';
                subcategoryInput.value = '';
                    document.querySelectorAll('.emotion-wheel button').forEach(btn => btn.classList.remove('is-active'));

                    // Show a customized modal with supportive messages then redirect to patient dashboard
                    try {
                        const catSlug = categoryInput?.value;
                        let catName = '';
                        let subName = '';
                        if (catSlug && Array.isArray(emotionOptions)) {
                            const cat = emotionOptions.find(c => c.slug === catSlug);
                            if (cat) {
                                catName = cat.name;
                                if (subcategoryInput?.value) {
                                    const sub = (cat.subcategories || []).find(s => s.slug === subcategoryInput.value);
                                    subName = sub ? sub.name : subcategoryInput.value;
                                }
                            }
                        }

                        // generate supportive messages based on category slug
                        const supportMessages = (() => {
                            const slug = catSlug || '';
                            switch (slug) {
                                case 'joy':
                                case 'rado%C5%9B%C4%87':
                                case 'radość':
                                    return [
                                        'Dobrze widzieć radość — celebrowanie małych zwycięstw pomaga utrzymać pozytywny nastrój.',
                                        'Podziel się swoją radością z kimś bliskim — pozytywne emocje rosną, kiedy są dzielone.'
                                    ];
                                case 'sadness':
                                case 'smutek':
                                    return [
                                        'Pozwól sobie poczuć to, co czujesz — to ważna część procesu zdrowienia.',
                                        'Jeśli chcesz, zapisz jedną rzecz, która dziś sprawiła Ci trochę ulgi.'
                                    ];
                                case 'anger':
                                case 'złość':
                                    return [
                                        'Złość to sygnał — spróbuj głębokiego oddechu przez 6 sekund, aby się uspokoić.',
                                        'Przemyśl jedną małą rzecz, którą możesz zmienić, żeby poczuć się lepiej.'
                                    ];
                                case 'anxiety':
                                case 'lęk':
                                    return [
                                        'Skoncentruj się na trzech rzeczach, które widzisz teraz — to pomaga wrócić do chwili obecnej.',
                                        'Jeśli lęk jest silny, spróbuj krótkiego spaceru lub ćwiczeń oddechowych.'
                                    ];
                                default:
                                    return [
                                        'Dziękuję za zapisanie — pamiętaj, że zauważanie emocji to ważny krok.',
                                        'Małe kroki każdego dnia prowadzą do długofalowych zmian.'
                                    ];
                            }
                        })();

                        const heading = subName ? `Zapisano emocję: ${catName} — ${subName}` : (catName ? `Zapisano emocję: ${catName}` : 'Zapisano emocję');
                        const subtitle = 'Dobrze, że to odnotowałaś/odnotowałeś — oto kilka wskazówek:';
                        showCenterModal(heading, subtitle, supportMessages, 2000, () => { window.location.href = '/patient/dashboard'; });
                    } catch (e) {
                        window.location.href = '/patient/dashboard';
                    }
            } else {
                showInlineMessage(moodForm, data.error ?? 'Nie udało się zapisać nastroju.', 'error');
            }
        } catch (error) {
            showInlineMessage(moodForm, 'Wystąpił błąd podczas zapisu nastroju.', 'error');
        }
    });

        initMoodChart();
        buildEmotionWheel();
        refreshHabits();
        updateTree();
        fetchChatMessages(true);
        startChatPolling();
    } catch (err) {
        console.error('patient-dashboard.js: uncaught error during init', err);
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPatientDashboard);
} else {
    // DOM already ready
    initPatientDashboard();
}
