/**
 * Emotion Display Component
 *
 * Визуализация результатов гибридного анализа эмоций:
 * - Детектированные сценарии с карточками
 * - Sentiment breakdown (менеджер/клиент)
 * - Audio characteristics
 * - Emotional trajectory
 */

class EmotionDisplay {
    constructor(containerSelector) {
        this.container = document.querySelector(containerSelector);
        this.scenarioIcons = {
            'conflict': '⚔️',
            'client_disappointment': '😞',
            'manager_burnout': '🥱',
            'client_distrust': '🤔',
            'categorical_rejection': '❌',
            'client_interest': '✨',
            'deal_readiness': '💰',
            'manager_trust': '🤝',
            'postpone_decision': '⏳',
            'repeat_listening': '🔁',
            'price_discussion': '💵',
            'location_clarification': '📍'
        };

        this.scenarioNames = {
            'conflict': 'КОНФЛИКТ',
            'client_disappointment': 'РАЗОЧАРОВАНИЕ КЛИЕНТА',
            'manager_burnout': 'ВЫГОРАНИЕ МЕНЕДЖЕРА',
            'client_distrust': 'НЕДОВЕРИЕ КЛИЕНТА',
            'categorical_rejection': 'КАТЕГОРИЧНЫЙ ОТКАЗ',
            'client_interest': 'ЗАИНТЕРЕСОВАННОСТЬ КЛИЕНТА',
            'deal_readiness': 'ГОТОВНОСТЬ К СДЕЛКЕ',
            'manager_trust': 'ДОВЕРИЕ К МЕНЕДЖЕРУ',
            'postpone_decision': 'ОТКЛАДЫВАНИЕ РЕШЕНИЯ',
            'repeat_listening': 'ПОВТОРНОЕ ПРОСЛУШИВАНИЕ',
            'price_discussion': 'ОБСУЖДЕНИЕ ЦЕНЫ',
            'location_clarification': 'УТОЧНЕНИЕ ЛОКАЦИИ'
        };

        this.scenarioCategories = {
            'negative': ['conflict', 'client_disappointment', 'manager_burnout', 'client_distrust', 'categorical_rejection'],
            'positive': ['client_interest', 'deal_readiness', 'manager_trust'],
            'process': ['postpone_decision', 'repeat_listening', 'price_discussion', 'location_clarification']
        };
    }

    /**
     * Загрузка и отображение эмоциональных данных
     */
    async loadAndDisplay(callid) {
        try {
            console.log(`🔄 Loading emotions for callid: ${callid}`);
            const response = await fetch(`/api/emotions.php?callid=${callid}`);

            if (!response.ok) {
                console.error(`❌ HTTP error: ${response.status} ${response.statusText}`);
                this.showError(`HTTP error: ${response.status}`);
                return;
            }

            const data = await response.json();
            console.log('📦 API response:', data);

            if (!data.success) {
                console.warn('⚠️ API returned success=false:', data.message);
                this.showError(data.message || 'Ошибка загрузки данных');
                return;
            }

            if (!data.has_emotion_data) {
                console.info('ℹ️ No emotion data available for this call');
                this.showNoData();
                return;
            }

            console.log('✅ Emotion data received, rendering...');
            this.render(data.emotion_data);

        } catch (error) {
            console.error('❌ Emotion display error:', error);
            console.error('Stack:', error.stack);
            this.showError('Ошибка загрузки эмоциональных данных');
        }
    }

    /**
     * Отображение "нет данных"
     */
    showNoData() {
        this.container.innerHTML = `
            <div class="emotion-alert emotion-alert-info">
                ℹ️ Эмоциональный анализ недоступен для этого звонка
            </div>
        `;
    }

    /**
     * Отображение ошибки
     */
    showError(message) {
        this.container.innerHTML = `
            <div class="emotion-alert emotion-alert-danger">
                ⚠️ ${message}
            </div>
        `;
    }

    /**
     * Главный метод рендеринга
     */
    render(emotionData) {
        try {
            console.log('🔍 Emotion data structure:', emotionData);

            const scenariosHtml = this.renderScenarios(emotionData);
            console.log('✅ Scenarios rendered');

            const sentimentHtml = this.renderSentimentBreakdown(emotionData);
            console.log('✅ Sentiment rendered');

            const audioHtml = this.renderAudioProfiles(emotionData);
            console.log('✅ Audio profiles rendered');

            const metricsHtml = this.renderOverallMetrics(emotionData);
            console.log('✅ Overall metrics rendered');

            const html = `
                <div class="emotion-analysis-container">
                    <h4>
                        🧠 Гибридный анализ эмоций (BERT + Audio)
                    </h4>

                    ${scenariosHtml}
                    ${sentimentHtml}
                    ${audioHtml}
                    ${metricsHtml}
                </div>
            `;

            this.container.innerHTML = html;
        } catch (error) {
            console.error('❌ Render error:', error);
            console.error('Stack:', error.stack);
            throw error; // Re-throw to be caught by loadAndDisplay
        }
    }

    /**
     * Рендеринг детектированных сценариев с карточками
     */
    renderScenarios(emotionData) {
        const scenarios = emotionData.scenarios || {};
        const confidences = emotionData.scenario_confidences || {};

        // Фильтруем только детектированные сценарии
        const detected = Object.keys(scenarios)
            .filter(key => scenarios[key])
            .map(key => ({
                key: key,
                confidence: confidences[key] || 0
            }))
            .sort((a, b) => b.confidence - a.confidence); // Сортируем по уверенности

        if (detected.length === 0) {
            return `
                <div class="emotion-alert emotion-alert-success">
                    ✅ Проблемных сценариев не обнаружено
                </div>
            `;
        }

        // Группируем по категориям
        const byCategory = {
            'negative': detected.filter(s => this.scenarioCategories.negative.includes(s.key)),
            'positive': detected.filter(s => this.scenarioCategories.positive.includes(s.key)),
            'process': detected.filter(s => this.scenarioCategories.process.includes(s.key))
        };

        let html = '<div class="scenarios-section">';
        html += '<h5>🎯 Детектированные сценарии:</h5>';
        html += '<div class="emotion-row">';

        // Негативные сценарии
        if (byCategory.negative.length > 0) {
            html += this.renderScenarioCategory('negative', '🚨 Проблемы', byCategory.negative);
        }

        // Позитивные сценарии
        if (byCategory.positive.length > 0) {
            html += this.renderScenarioCategory('positive', '✅ Позитив', byCategory.positive);
        }

        // Процессуальные сценарии
        if (byCategory.process.length > 0) {
            html += this.renderScenarioCategory('process', '📋 Процесс', byCategory.process);
        }

        html += '</div></div>';

        return html;
    }

    /**
     * Рендеринг категории сценариев
     */
    renderScenarioCategory(category, title, scenarios) {
        const colorClass = {
            'negative': 'danger',
            'positive': 'success',
            'process': 'info'
        }[category];

        let html = `
            <div class="emotion-col">
                <div class="emotion-card">
                    <div class="emotion-card-header emotion-card-header-${colorClass}">
                        <strong>${title}</strong>
                    </div>
                    <div class="emotion-card-body">
        `;

        scenarios.forEach(scenario => {
            const icon = this.scenarioIcons[scenario.key] || '•';
            const name = this.scenarioNames[scenario.key] || scenario.key;
            const confidence = Math.round(scenario.confidence * 100);

            html += `
                <div class="scenario-item">
                    <div class="scenario-item-header">
                        <span>
                            <span class="scenario-icon">${icon}</span>
                            <strong>${name}</strong>
                        </span>
                        <span class="emotion-badge emotion-badge-${colorClass}">${confidence}%</span>
                    </div>
                    <div class="emotion-progress">
                        <div class="emotion-progress-bar emotion-progress-bar-${colorClass}"
                             style="width: ${confidence}%"></div>
                    </div>
                </div>
            `;
        });

        html += `
                    </div>
                </div>
            </div>
        `;

        return html;
    }

    /**
     * Рендеринг sentiment breakdown
     */
    renderSentimentBreakdown(emotionData) {
        const managerSentiment = emotionData.manager_sentiment || {};
        const clientSentiment = emotionData.client_sentiment || {};

        return `
            <div class="sentiment-section">
                <h5>💬 Sentiment Analysis (BERT):</h5>
                <div class="emotion-row">
                    <div class="emotion-col">
                        <div class="emotion-card">
                            <div class="emotion-card-body">
                                <h6>👔 Менеджер</h6>
                                ${this.renderSentimentBars(managerSentiment)}
                            </div>
                        </div>
                    </div>
                    <div class="emotion-col">
                        <div class="emotion-card">
                            <div class="emotion-card-body">
                                <h6>👤 Клиент</h6>
                                ${this.renderSentimentBars(clientSentiment)}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Рендеринг sentiment bars
     */
    renderSentimentBars(sentiment) {
        const sentiments = [
            { key: 'POSITIVE', label: 'Позитив', color: 'success' },
            { key: 'NEUTRAL', label: 'Нейтрал', color: 'secondary' },
            { key: 'NEGATIVE', label: 'Негатив', color: 'danger' }
        ];

        let html = '';
        sentiments.forEach(s => {
            const value = sentiment[s.key] || 0;
            const percent = Math.round(value * 100);

            html += `
                <div class="sentiment-bar-wrapper">
                    <div class="sentiment-bar-header">
                        <small>${s.label}</small>
                        <strong>${percent}%</strong>
                    </div>
                    <div class="sentiment-bar">
                        <div class="sentiment-bar-inner emotion-progress-bar-${s.color}"
                             style="width: ${percent}%"></div>
                    </div>
                </div>
            `;
        });

        return html;
    }

    /**
     * Рендеринг audio профилей
     */
    renderAudioProfiles(emotionData) {
        const managerAudio = emotionData.manager_audio_profile || emotionData.manager_audio || {};
        const clientAudio = emotionData.client_audio_profile || emotionData.client_audio || {};

        return `
            <div class="audio-section">
                <h5>🎤 Audio Characteristics (librosa):</h5>
                <div class="emotion-row">
                    <div class="emotion-col">
                        <div class="emotion-card">
                            <div class="emotion-card-body">
                                <h6>👔 Менеджер</h6>
                                ${this.renderAudioMetrics(managerAudio)}
                            </div>
                        </div>
                    </div>
                    <div class="emotion-col">
                        <div class="emotion-card">
                            <div class="emotion-card-body">
                                <h6>👤 Клиент</h6>
                                ${this.renderAudioMetrics(clientAudio)}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Рендеринг audio метрик
     */
    renderAudioMetrics(audio) {
        const metrics = [
            { key: 'pitch_mean', label: 'Высота тона', unit: 'Hz', format: v => Math.round(v) },
            { key: 'energy_mean', label: 'Громкость', unit: '', format: v => v.toFixed(3) },
            { key: 'speaking_rate', label: 'Темп речи', unit: 'BPM', format: v => Math.round(v) },
            { key: 'voice_brightness', label: 'Яркость голоса', unit: 'Hz', format: v => Math.round(v) }
        ];

        let html = '<dl class="emotion-metrics">';
        metrics.forEach(m => {
            const value = audio[m.key];
            if (value !== undefined && value !== null && value > 0) {
                html += `
                    <dt>${m.label}:</dt>
                    <dd>
                        <strong>${m.format(value)}</strong>
                        <small>${m.unit}</small>
                    </dd>
                `;
            }
        });
        html += '</dl>';

        return html;
    }

    /**
     * Рендеринг общих метрик
     */
    renderOverallMetrics(emotionData) {
        const overall = emotionData.overall_sentiment || 'NEUTRAL';
        const intensity = emotionData.emotional_intensity || 0;

        const overallColors = {
            'POSITIVE': 'success',
            'NEUTRAL': 'secondary',
            'NEGATIVE': 'danger'
        };

        const overallLabels = {
            'POSITIVE': 'Позитивный',
            'NEUTRAL': 'Нейтральный',
            'NEGATIVE': 'Негативный'
        };

        return `
            <div class="overall-section">
                <h5>📊 Общие метрики:</h5>
                <div class="emotion-row">
                    <div class="emotion-col">
                        <div class="emotion-overall-card">
                            <h6>Общий тон разговора</h6>
                            <h3 class="emotion-text-${overallColors[overall]}">
                                ${overallLabels[overall]}
                            </h3>
                        </div>
                    </div>
                    <div class="emotion-col">
                        <div class="emotion-overall-card">
                            <h6>Эмоциональная интенсивность</h6>
                            <h3 class="emotion-text-info">
                                ${Math.round(intensity * 100)}%
                            </h3>
                            <div class="emotion-progress" style="height: 20px; margin-top: 12px;">
                                <div class="emotion-progress-bar emotion-progress-bar-info"
                                     style="width: ${Math.round(intensity * 100)}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
}

// Export для использования в других скриптах
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EmotionDisplay;
}
