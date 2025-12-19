/**
 * Psychologist Dashboard - Analysis Page Script
 * Handles chart rendering, patient selection, and analysis entries
 */

document.addEventListener('DOMContentLoaded', () => {
    // Find container (supports both old and new class names)
    const container = document.querySelector('.dashboard-grid--analysis') || document.querySelector('.dashboard-grid--psychologist');
    if (!(container instanceof HTMLElement)) return;

    const userId = Number(container.dataset.userId || '0');
    
    // Chart elements
    const insightCanvas = document.querySelector('#psychologist-mood-chart');
    const chartPlaceholder = document.querySelector('#chart-placeholder');
    const chartLegend = document.querySelector('#insight-legend');
    const chartLoading = document.querySelector('#insight-loading');
    
    // State
    let insightChart = null;
    let currentPatientId = null;
    let currentAnchor = new Date().toISOString().slice(0, 10);
    let currentMode = 'daily';

    // Try to restore mode from localStorage
    try {
        const saved = localStorage.getItem('psy_insight_mode');
        if (saved && ['daily', 'weekly', 'monthly', 'yearly'].includes(saved)) {
            currentMode = saved;
        }
    } catch (e) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Chart Rendering
    // ─────────────────────────────────────────────────────────────────────────
    const renderChart = (history) => {
        if (!window.Chart || !insightCanvas) return;

        // Hide placeholder, show canvas
        if (chartPlaceholder) chartPlaceholder.style.display = 'none';
        if (chartLegend) chartLegend.classList.remove('is-hidden');
        insightCanvas.style.display = 'block';

        const labels = history.map(p => p.date).reverse();
        const moods = history.map(p => Number(p.average_mood ?? 0)).reverse();
        const intensity = history.map(p => Number(p.average_intensity ?? 0)).reverse();
        const dominant = history.map(p => p.dominant_category ?? null).reverse();

        // Build emotion color map
        const emotionMap = (window.__emotionOptions || []).reduce((acc, e) => {
            if (e?.name) acc[e.name] = e.accentColor || e.accent || '#2a9d8f';
            return acc;
        }, {});

        const moodColor = '#2a9d8f';
        const intensityColor = '#f4a261';

        // Per-point styling based on dominant emotion
        const moodPointColors = dominant.map(d => d ? (emotionMap[d] || moodColor) : moodColor);
        const moodPointRadius = dominant.map(d => d ? 6 : 4);

        // Destroy previous chart
        insightChart?.destroy();

        insightChart = new Chart(insightCanvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Nastrój',
                        data: moods,
                        borderColor: moodColor,
                        backgroundColor: 'rgba(42, 157, 143, 0.12)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: moodPointColors,
                        pointRadius: moodPointRadius,
                        pointHoverRadius: 8,
                        borderWidth: 3,
                    },
                    {
                        label: 'Intensywność',
                        data: intensity,
                        borderColor: intensityColor,
                        backgroundColor: 'rgba(244, 162, 97, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: 10,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: { font: { weight: '500' } },
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                const idx = ctx.dataIndex;
                                const dom = dominant[idx];
                                return ctx.dataset.label + ': ' + ctx.formattedValue + (dom ? ' • ' + dom : '');
                            }
                        }
                    }
                }
            },
        });
    };

    // ─────────────────────────────────────────────────────────────────────────
    // Data Loading
    // ─────────────────────────────────────────────────────────────────────────
    const chartDateInput = document.querySelector('#chart-date');
    
    // Format helpers for different modes
    const formatDateForMode = (isoDate, mode) => {
        const d = new Date(isoDate);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        
        switch (mode) {
            case 'yearly':
                return String(year);
            case 'monthly':
                return `${month}.${year}`;
            case 'weekly':
                // Show start of week (Monday)
                const monday = new Date(d);
                const dayOfWeek = monday.getDay();
                const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
                monday.setDate(monday.getDate() + diff);
                const mDay = String(monday.getDate()).padStart(2, '0');
                const mMonth = String(monday.getMonth() + 1).padStart(2, '0');
                const mYear = monday.getFullYear();
                return `${mDay}.${mMonth}.${mYear}`;
            case 'daily':
            default:
                return `${day}.${month}.${year}`;
        }
    };

    const parseInputToIso = (value, mode) => {
        value = value.trim();
        
        switch (mode) {
            case 'yearly':
                // Format: YYYY or RRRR
                if (/^\d{4}$/.test(value)) {
                    return `${value}-01-01`;
                }
                break;
            case 'monthly':
                // Format: MM.YYYY or M.YYYY
                const monthMatch = value.match(/^(\d{1,2})\.(\d{4})$/);
                if (monthMatch) {
                    const m = String(monthMatch[1]).padStart(2, '0');
                    return `${monthMatch[2]}-${m}-01`;
                }
                break;
            case 'weekly':
            case 'daily':
            default:
                // Format: DD.MM.YYYY or D.M.YYYY
                const dayMatch = value.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);
                if (dayMatch) {
                    const dd = String(dayMatch[1]).padStart(2, '0');
                    const mm = String(dayMatch[2]).padStart(2, '0');
                    return `${dayMatch[3]}-${mm}-${dd}`;
                }
                break;
        }
        return null;
    };

    const getPlaceholderForMode = (mode) => {
        switch (mode) {
            case 'yearly': return 'rrrr';
            case 'monthly': return 'mm.rrrr';
            case 'weekly':
            case 'daily':
            default: return 'dd.mm.rrrr';
        }
    };

    // Sync date input with current anchor
    const updateDateInput = () => {
        if (chartDateInput) {
            chartDateInput.value = formatDateForMode(currentAnchor, currentMode);
            chartDateInput.placeholder = getPlaceholderForMode(currentMode);
        }
    };

    const loadInsights = async (patientId, mode, anchor) => {
        if (!patientId) return;

        // Show loading
        chartLoading?.classList.remove('is-hidden');

        try {
            const qp = new URLSearchParams({ mode: mode || 'daily' });
            if (anchor) qp.set('anchor', anchor);

            const response = await fetch(`/psychologist/patient/${patientId}/moods?${qp.toString()}`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();

            if (!response.ok) throw new Error(data.error ?? 'Błąd pobierania danych');

            // Update anchor and date input
            if (data.anchor) {
                currentAnchor = data.anchor;
                updateDateInput();
            }

            // Handle empty data
            if (!data.history || data.history.length === 0) {
                if (chartPlaceholder) {
                    chartPlaceholder.innerHTML = '<div class="placeholder-icon">📭</div><p>Brak wpisów nastroju w tym okresie</p>';
                    chartPlaceholder.style.display = '';
                }
                if (insightCanvas) insightCanvas.style.display = 'none';
                if (chartLegend) chartLegend.classList.add('is-hidden');
                return;
            }

            renderChart(data.history);

        } catch (error) {
            console.error('Error loading insights:', error);
            if (chartPlaceholder) {
                chartPlaceholder.innerHTML = '<div class="placeholder-icon">⚠️</div><p>Nie udało się załadować danych</p>';
                chartPlaceholder.style.display = '';
            }
        } finally {
            chartLoading?.classList.add('is-hidden');
        }
    };

    // ─────────────────────────────────────────────────────────────────────────
    // Patient Selection
    // ─────────────────────────────────────────────────────────────────────────
    const patientSelector = document.querySelector('#patient-selector');
    const analysisForm = document.querySelector('#analysis-entry-form');
    const patientHiddenInput = document.querySelector('input[name="patient_user_id"]');

    const findPatientId = (userIdValue) => {
        const patients = window.__patients || [];
        const p = patients.find(pt => String(pt.patient_user_id) === String(userIdValue));
        return p ? p.patient_id : null;
    };

    // Helper elements for entries UI (needed early)
    const addEntryBtnEl = document.querySelector('#add-entry-btn');
    const entriesToolbarEl = document.querySelector('#entries-toolbar');
    const entriesEmptyEl = document.querySelector('#entries-empty');
    const entriesListEl = document.querySelector('#analysis-entries-list');
    const formPatientIdEl = document.querySelector('#form-patient-id');

    // Update entries section UI based on patient selection
    const updateEntriesUI = (hasPatient) => {
        if (addEntryBtnEl) addEntryBtnEl.style.display = hasPatient ? '' : 'none';
        if (entriesToolbarEl) entriesToolbarEl.style.display = hasPatient ? '' : 'none';
        if (entriesEmptyEl) {
            if (hasPatient) {
                const hasEntries = entriesListEl?.querySelectorAll('.analysis-entry').length > 0;
                entriesEmptyEl.style.display = hasEntries ? 'none' : '';
                if (!hasEntries) {
                    entriesEmptyEl.innerHTML = '<div class="entries-empty__icon">📝</div><p>Brak wpisów analizy. Kliknij "+ Dodaj wpis" aby utworzyć pierwszy.</p>';
                }
            } else {
                entriesEmptyEl.style.display = '';
                entriesEmptyEl.innerHTML = '<div class="entries-empty__icon">📝</div><p>Wybierz pacjenta, aby zobaczyć i dodawać wpisy analizy</p>';
            }
        }
    };

    const resetToEmptyState = () => {
        currentPatientId = null;
        if (analysisForm) analysisForm.style.display = 'none';
        if (chartPlaceholder) {
            chartPlaceholder.innerHTML = '<div class="placeholder-icon">📊</div><p>Wybierz pacjenta z listy powyżej, aby zobaczyć trend emocji</p>';
            chartPlaceholder.style.display = '';
        }
        if (insightCanvas) insightCanvas.style.display = 'none';
        if (chartLegend) chartLegend.classList.add('is-hidden');
        if (periodLabel) periodLabel.textContent = 'Wybierz pacjenta';
        updateEntriesUI(false);
    };

    // Load analysis entries for patient
    const loadAnalysisEntries = async (patientUserId) => {
        if (!patientUserId || !entriesListEl) return;
        
        try {
            const response = await fetch(`/psychologist/patient/${patientUserId}/analysis-entries`, {
                headers: { 'Accept': 'application/json' },
            });
            
            if (!response.ok) {
                console.error('Failed to load entries');
                return;
            }
            
            const data = await response.json();
            if (data.status !== 'ok' || !data.entries) return;
            
            // Clear existing entries
            entriesListEl.innerHTML = '';
            
            if (data.entries.length === 0) {
                updateEntriesUI(true); // Will show empty state message
                return;
            }
            
            // Render entries
            data.entries.forEach(entry => {
                const article = document.createElement('article');
                article.className = 'analysis-entry';
                article.dataset.entryId = entry.id;
                article.dataset.date = entry.entry_date;
                
                const formattedDate = new Date(entry.entry_date).toLocaleDateString('pl-PL');
                const createdAt = new Date(entry.created_at).toLocaleString('pl-PL');
                const updatedAt = new Date(entry.updated_at);
                const createdAtDate = new Date(entry.created_at);
                let updatedHtml = '';
                if (updatedAt > createdAtDate) {
                    updatedHtml = `<small>Zaktualizowano: ${updatedAt.toLocaleString('pl-PL')}</small>`;
                }
                
                article.innerHTML = `
                    <header class="analysis-entry__header">
                        <div>
                            <h3>${escapeHtml(entry.title)}</h3>
                            <time datetime="${entry.entry_date}">${formattedDate}</time>
                        </div>
                        <div class="analysis-entry__actions">
                            <button type="button" class="button button--ghost button--small edit-entry-btn" data-entry-id="${entry.id}">Edytuj</button>
                            <button type="button" class="button button--ghost button--small delete-entry-btn" data-entry-id="${entry.id}">Usuń</button>
                        </div>
                    </header>
                    <div class="analysis-entry__content">${escapeHtml(entry.content || '').replace(/\n/g, '<br>')}</div>
                    <footer class="analysis-entry__footer">
                        <small>Utworzono: ${createdAt}</small>
                        ${updatedHtml}
                    </footer>
                `;
                
                entriesListEl.appendChild(article);
            });
            
            // Hide empty state and apply initial sort/filter
            if (entriesEmptyEl) entriesEmptyEl.style.display = 'none';
            
        } catch (error) {
            console.error('Error loading entries:', error);
        }
    };

    // Helper function for escaping HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    if (patientSelector instanceof HTMLSelectElement) {
        patientSelector.addEventListener('change', () => {
            const val = patientSelector.value;

            if (!val) {
                resetToEmptyState();
                return;
            }

            // Update hidden inputs
            if (patientHiddenInput) patientHiddenInput.value = val;
            if (formPatientIdEl) formPatientIdEl.value = val;

            // Show entries UI
            updateEntriesUI(true);
            
            // Load entries for this patient
            loadAnalysisEntries(val);

            // Find internal patient ID
            const pid = findPatientId(val);
            if (!pid) {
                console.warn('Patient not found for user_id:', val);
                return;
            }

            currentPatientId = pid;
            currentAnchor = new Date().toISOString().slice(0, 10);

            // Load chart
            loadInsights(pid, currentMode, currentAnchor);
        });

        // Auto-trigger if pre-selected
        if (patientSelector.value) {
            patientSelector.dispatchEvent(new Event('change'));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Timeframe Controls
    // ─────────────────────────────────────────────────────────────────────────
    const timeframeSegment = document.querySelector('.timeframe-segment');

    if (timeframeSegment) {
        // Set initial active state
        const initBtn = timeframeSegment.querySelector(`.segment-btn[data-mode="${currentMode}"]`);
        if (initBtn) {
            timeframeSegment.querySelectorAll('.segment-btn').forEach(b => b.classList.remove('active'));
            initBtn.classList.add('active');
        }

        timeframeSegment.addEventListener('click', (e) => {
            const btn = e.target.closest('.segment-btn');
            if (!btn) return;

            // Update active state
            timeframeSegment.querySelectorAll('.segment-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Save and apply new mode
            currentMode = btn.dataset.mode || 'daily';
            try { localStorage.setItem('psy_insight_mode', currentMode); } catch (e) {}

            // Reset anchor to today and reload
            currentAnchor = new Date().toISOString().slice(0, 10);
            updateDateInput();
            if (currentPatientId) {
                loadInsights(currentPatientId, currentMode, currentAnchor);
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Period Navigation
    // ─────────────────────────────────────────────────────────────────────────
    const chartPrev = document.querySelector('#chart-prev');
    const chartNext = document.querySelector('#chart-next');

    const navigatePeriod = (direction) => {
        if (!currentPatientId) return;

        const anchor = new Date(currentAnchor);

        switch (currentMode) {
            case 'daily':
                anchor.setDate(anchor.getDate() + direction);
                break;
            case 'weekly':
                anchor.setDate(anchor.getDate() + direction * 7);
                break;
            case 'monthly':
                anchor.setMonth(anchor.getMonth() + direction);
                break;
            case 'yearly':
                anchor.setFullYear(anchor.getFullYear() + direction);
                break;
        }

        currentAnchor = anchor.toISOString().slice(0, 10);
        updateDateInput();
        loadInsights(currentPatientId, currentMode, currentAnchor);
    };

    chartPrev?.addEventListener('click', () => navigatePeriod(-1));
    chartNext?.addEventListener('click', () => navigatePeriod(1));

    // Handle manual date input (supports mode-specific formats)
    const handleDateInput = () => {
        const val = chartDateInput?.value;
        if (!val) return;
        
        const iso = parseInputToIso(val, currentMode);
        if (iso) {
            currentAnchor = iso;
            if (currentPatientId) {
                loadInsights(currentPatientId, currentMode, currentAnchor);
            }
        } else {
            // Invalid format - restore previous value
            updateDateInput();
        }
    };

    chartDateInput?.addEventListener('change', handleDateInput);
    chartDateInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleDateInput();
            chartDateInput.blur();
        }
    });
    // ─────────────────────────────────────────────────────────────────────────
    // Analysis Entries Section
    // ─────────────────────────────────────────────────────────────────────────
    // Note: Main UI elements (addEntryBtnEl, entriesToolbarEl, entriesEmptyEl, entriesListEl, formPatientIdEl) 
    // and updateEntriesUI() are defined earlier in Patient Selection section
    
    const entriesNoResults = document.querySelector('#entries-no-results');
    const cancelEntryBtn = document.querySelector('#cancel-entry-btn');
    const entriesSearch = document.querySelector('#entries-search');
    const entriesMonthFilter = document.querySelector('#entries-month-filter');
    const entriesSortSelect = document.querySelector('#entries-sort');

    // Filter and sort entries
    const filterAndSortEntries = () => {
        if (!entriesListEl) return;
        
        const searchTerm = entriesSearch?.value.toLowerCase().trim() || '';
        const monthFilter = entriesMonthFilter?.value || ''; // YYYY-MM format
        const sortOrder = entriesSortSelect?.value || 'newest';
        
        const entries = Array.from(entriesListEl.querySelectorAll('.analysis-entry'));
        let visibleCount = 0;
        
        entries.forEach(entry => {
            const title = entry.querySelector('h3')?.textContent?.toLowerCase() || '';
            const content = entry.querySelector('.analysis-entry__content')?.textContent?.toLowerCase() || '';
            const dateText = entry.querySelector('time')?.textContent || '';
            
            // Parse date (format: DD.MM.YYYY or YYYY-MM-DD)
            let entryMonth = '';
            const dateParts = dateText.match(/(\d{2})\.(\d{2})\.(\d{4})/);
            if (dateParts) {
                entryMonth = `${dateParts[3]}-${dateParts[2]}`;
            } else {
                const isoMatch = dateText.match(/(\d{4})-(\d{2})-(\d{2})/);
                if (isoMatch) {
                    entryMonth = `${isoMatch[1]}-${isoMatch[2]}`;
                }
            }
            
            // Check filters
            const matchesSearch = !searchTerm || title.includes(searchTerm) || content.includes(searchTerm);
            const matchesMonth = !monthFilter || entryMonth === monthFilter;
            
            if (matchesSearch && matchesMonth) {
                entry.style.display = '';
                visibleCount++;
            } else {
                entry.style.display = 'none';
            }
        });
        
        // Sort visible entries
        const visibleEntries = entries.filter(e => e.style.display !== 'none');
        visibleEntries.sort((a, b) => {
            const dateA = a.querySelector('time')?.textContent || '';
            const dateB = b.querySelector('time')?.textContent || '';
            
            // Parse dates for comparison
            const parseDate = (str) => {
                const parts = str.match(/(\d{2})\.(\d{2})\.(\d{4})/);
                if (parts) return new Date(parts[3], parts[2] - 1, parts[1]);
                return new Date(str);
            };
            
            const dA = parseDate(dateA);
            const dB = parseDate(dateB);
            
            return sortOrder === 'newest' ? dB - dA : dA - dB;
        });
        
        // Reorder DOM
        visibleEntries.forEach(entry => entriesListEl.appendChild(entry));
        
        // Show/hide no results message
        if (entriesNoResults) {
            entriesNoResults.style.display = (entries.length > 0 && visibleCount === 0) ? '' : 'none';
        }
        if (entriesEmptyEl && entries.length > 0) {
            entriesEmptyEl.style.display = 'none';
        }
    };

    // Event listeners for filters
    entriesSearch?.addEventListener('input', filterAndSortEntries);
    entriesMonthFilter?.addEventListener('change', filterAndSortEntries);
    entriesSortSelect?.addEventListener('change', filterAndSortEntries);

    // Add entry button
    addEntryBtnEl?.addEventListener('click', () => {
        if (analysisForm) {
            analysisForm.style.display = '';
            analysisForm.reset();
            document.querySelector('#entry-id').value = '';
            document.querySelector('#entry-date').value = new Date().toISOString().slice(0, 10);
            document.querySelector('#form-submit-btn').textContent = 'Zapisz wpis';
            analysisForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    // Cancel button
    cancelEntryBtn?.addEventListener('click', () => {
        if (analysisForm) {
            analysisForm.style.display = 'none';
            analysisForm.reset();
        }
    });

    // Form submit
    if (analysisForm instanceof HTMLFormElement) {
        analysisForm.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            
            const submitBtn = analysisForm.querySelector('button[type="submit"]');
            const originalText = submitBtn?.textContent;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Zapisywanie...';
            }

            try {
                const formData = new FormData(analysisForm);
                const entryId = formData.get('entry_id');
                const isEdit = entryId && entryId !== '';
                
                const response = await fetch('/psychologist/analysis/entry', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData,
                });
                const data = await response.json();

                if (!response.ok) {
                    alert(data.error ?? 'Nie udało się zapisać wpisu.');
                    return;
                }

                const entry = data.entry;
                
                // If editing, remove old entry
                if (isEdit) {
                    const oldEntry = entriesListEl?.querySelector(`[data-entry-id="${entryId}"]`);
                    oldEntry?.remove();
                }
                
                // Create entry element
                const article = document.createElement('article');
                article.className = 'analysis-entry';
                article.dataset.entryId = entry.id;
                article.dataset.date = entry.entry_date;

                const formattedDate = new Date(entry.entry_date).toLocaleDateString('pl-PL');
                
                article.innerHTML = `
                    <header class="analysis-entry__header">
                        <div>
                            <h3>${escapeHtml(entry.title)}</h3>
                            <time datetime="${entry.entry_date}">${formattedDate}</time>
                        </div>
                        <div class="analysis-entry__actions">
                            <button type="button" class="button button--ghost button--small edit-entry-btn" data-entry-id="${entry.id}">Edytuj</button>
                            <button type="button" class="button button--ghost button--small delete-entry-btn" data-entry-id="${entry.id}">Usuń</button>
                        </div>
                    </header>
                    <div class="analysis-entry__content">${escapeHtml(entry.content || '').replace(/\n/g, '<br>')}</div>
                    <footer class="analysis-entry__footer">
                        <small>Utworzono: ${new Date(entry.created_at).toLocaleString('pl-PL')}</small>
                    </footer>
                `;

                // Add to list
                if (entriesListEl) {
                    entriesListEl.insertAdjacentElement('afterbegin', article);
                }

                // Hide form and empty state
                analysisForm.style.display = 'none';
                analysisForm.reset();
                if (entriesEmptyEl) entriesEmptyEl.style.display = 'none';
                
                // Re-sort
                filterAndSortEntries();

            } catch (error) {
                console.error('Error saving entry:', error);
                alert('Nie udało się zapisać wpisu.');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Entry Edit/Delete (Event Delegation)
    // ─────────────────────────────────────────────────────────────────────────
    entriesListEl?.addEventListener('click', async (e) => {
        const target = e.target;
        
        // Edit button
        if (target.matches('.edit-entry-btn')) {
            const entryId = target.dataset.entryId;
            const article = target.closest('.analysis-entry');
            if (!article) return;

            const title = article.querySelector('h3')?.textContent || '';
            const timeEl = article.querySelector('time');
            const date = timeEl?.getAttribute('datetime') || timeEl?.textContent || '';
            const content = article.querySelector('.analysis-entry__content')?.textContent || '';

            document.querySelector('#entry-id').value = entryId;
            document.querySelector('#entry-title').value = title;
            document.querySelector('#entry-date').value = date;
            document.querySelector('#entry-content').value = content;
            document.querySelector('#form-submit-btn').textContent = 'Zaktualizuj wpis';

            if (analysisForm) {
                analysisForm.style.display = '';
                analysisForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // Delete button
        if (target.matches('.delete-entry-btn')) {
            const entryId = target.dataset.entryId;
            if (!confirm('Czy na pewno chcesz usunąć ten wpis?')) return;

            try {
                const response = await fetch(`/psychologist/analysis/entry/${entryId}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json' },
                });

                if (response.ok) {
                    const article = target.closest('.analysis-entry');
                    article?.remove();
                    
                    // Check if list is now empty
                    const remaining = entriesListEl?.querySelectorAll('.analysis-entry').length || 0;
                    if (remaining === 0 && entriesEmptyEl) {
                        entriesEmptyEl.style.display = '';
                        entriesEmptyEl.innerHTML = '<div class="entries-empty__icon">📝</div><p>Brak wpisów analizy. Kliknij "+ Dodaj wpis" aby utworzyć pierwszy.</p>';
                    }
                } else {
                    const data = await response.json();
                    alert(data.error ?? 'Nie udało się usunąć wpisu.');
                }
            } catch (error) {
                console.error('Error deleting entry:', error);
                alert('Nie udało się usunąć wpisu.');
            }
        }
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Chat Functionality (legacy support)
    // ─────────────────────────────────────────────────────────────────────────
    const chatWindow = document.querySelector('#psychologist-chat-window');
    const chatMessages = document.querySelector('#psychologist-chat-messages');
    const chatForm = document.querySelector('#psychologist-chat-form');
    const chatPatientInput = document.querySelector('#chat-patient-id');
    const chatMessageInput = chatForm?.querySelector('input[name="message"]');
    const chatSendButton = document.querySelector('#chat-send-button');
    const chatRefreshButton = document.querySelector('#psychologist-chat-refresh');
    const patientTable = document.querySelector('#patient-table');

    let currentPatientUserId = null;
    let chatThreadId = null;
    let lastMessageId = 0;
    let pollingHandle = null;

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
            meta.textContent = new Date(message.created_at).toLocaleString();
            item.append(body, meta);
            chatMessages.append(item);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            lastMessageId = Math.max(lastMessageId, message.id);
        });
    };

    const fetchChatMessages = async (initial = false) => {
        if (!currentPatientUserId) return;
        try {
            const params = new URLSearchParams({ patient_id: String(currentPatientUserId) });
            if (!initial && lastMessageId > 0) {
                params.set('after_id', String(lastMessageId));
            }

            const response = await fetch(`/api/chat/messages?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error('HTTP error');

            const data = await response.json();
            if (data.status !== 'ok') return;

            chatThreadId = data.thread_id;
            if (initial) {
                chatMessages.innerHTML = '';
                lastMessageId = 0;
            }

            renderChatMessages(data.messages ?? []);
        } catch (error) {
            console.warn('Failed to fetch messages:', error);
        }
    };

    const startPolling = () => {
        if (pollingHandle) clearInterval(pollingHandle);
        pollingHandle = window.setInterval(() => fetchChatMessages(false), 5000);
    };

    // Patient table click handler (for chat)
    patientTable?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;

        const insightButton = target.closest('[data-patient-insights]');
        if (insightButton) {
            const patientId = insightButton.dataset.patientInsights;
            if (patientId) loadInsights(patientId, currentMode, currentAnchor);
            return;
        }

        const chatButton = target.closest('[data-patient-chat]');
        if (chatButton) {
            const row = chatButton.closest('.patient-row');
            if (!row) return;
            currentPatientUserId = Number(row.dataset.patientUserId);
            const currentPatientName = row.dataset.patientName ?? 'Pacjent';
            if (chatPatientInput) chatPatientInput.value = String(currentPatientUserId);
            if (chatMessageInput) chatMessageInput.value = '';
            if (chatSendButton) chatSendButton.disabled = false;
            if (chatMessages) chatMessages.innerHTML = '';
            lastMessageId = 0;
            fetchChatMessages(true);
            startPolling();
            chatWindow?.classList.add('is-ready');
        }
    });

    chatForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!currentPatientUserId || !chatMessageInput?.value.trim()) return;

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
                if (chatMessageInput) chatMessageInput.value = '';
            } else {
                alert(data.error ?? 'Nie udało się wysłać wiadomości.');
            }
        } catch (error) {
            console.warn('Error sending message:', error);
        }
    });

    chatRefreshButton?.addEventListener('click', (event) => {
        event.preventDefault();
        fetchChatMessages(true);
    });

    // Expose for global access if needed
    window.loadInsights = loadInsights;
    window.renderChart = renderChart;
});
