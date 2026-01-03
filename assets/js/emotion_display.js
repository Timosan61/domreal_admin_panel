/**
 * Emotion Display Component
 *
 * Визуализация базовых эмоциональных показателей:
 * - Sentiment breakdown (менеджер/клиент)
 * - Audio characteristics (тон, громкость, темп)
 * - Общие метрики (тон разговора, интенсивность)
 *
 * Используется как контекст для комплексного анализа вместе с транскрипцией.
 */

class EmotionDisplay {
    constructor(containerSelector) {
        this.container = document.querySelector(containerSelector);
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
     * Главный метод рендеринга (только базовые показатели)
     */
    render(emotionData) {
        try {
            console.log('🔍 Emotion data structure:', emotionData);

            const metricsHtml = this.renderOverallMetrics(emotionData);
            console.log('✅ Overall metrics rendered');

            const indicatorsHtml = this.renderIndicators(emotionData);
            console.log('✅ Indicators rendered');

            const sentimentHtml = this.renderSentimentBreakdown(emotionData);
            console.log('✅ Sentiment rendered');

            const audioHtml = this.renderAudioProfiles(emotionData);
            console.log('✅ Audio profiles rendered');

            const html = `
                <div class="emotion-analysis-container">
                    <h4>
                        🎭 Эмоциональный контекст разговора
                    </h4>

                    ${metricsHtml}
                    ${indicatorsHtml}
                    ${sentimentHtml}
                    ${audioHtml}
                </div>
            `;

            this.container.innerHTML = html;
        } catch (error) {
            console.error('❌ Render error:', error);
            console.error('Stack:', error.stack);
            throw error;
        }
    }

    /**
     * Рендеринг индикаторов перебивания и конфликта
     */
    renderIndicators(emotionData) {
        const scenarios = emotionData.scenarios || {};
        const confidences = emotionData.scenario_confidences || {};

        // Конфликт
        const hasConflict = scenarios.conflict || false;
        const conflictConfidence = Math.round((confidences.conflict || 0) * 100);

        // Перебивание - проверяем из audio профилей или scenarios
        const managerAudio = emotionData.manager_audio_profile || emotionData.manager_audio || {};
        const clientAudio = emotionData.client_audio_profile || emotionData.client_audio || {};

        // Вычисляем overlap из speaking_rate или другого источника
        const overlapScore = emotionData.overlap_score || emotionData.interruption_score || 0;
        const hasInterruptions = overlapScore > 0.3;

        let html = '<div class="emotion-indicators-row">';

        // Индикатор конфликта
        if (hasConflict) {
            html += `
                <div class="emotion-indicator emotion-indicator-danger">
                    ⚔️ Конфликт обнаружен (${conflictConfidence}%)
                </div>
            `;
        } else {
            html += `
                <div class="emotion-indicator emotion-indicator-ok">
                    ✅ Без конфликта
                </div>
            `;
        }

        // Индикатор перебивания
        if (hasInterruptions) {
            const overlapPercent = Math.round(overlapScore * 100);
            html += `
                <div class="emotion-indicator emotion-indicator-warning">
                    🗣️ Перебивания (${overlapPercent}%)
                </div>
            `;
        } else {
            html += `
                <div class="emotion-indicator emotion-indicator-ok">
                    ✅ Без перебивания
                </div>
            `;
        }

        html += '</div>';

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

        // Проверяем есть ли хоть какие-то audio данные
        const hasManagerAudio = this.hasAudioData(managerAudio);
        const hasClientAudio = this.hasAudioData(clientAudio);

        // Если нет audio данных (text-only анализ) - не показываем блок
        if (!hasManagerAudio && !hasClientAudio) {
            return '';
        }

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
     * Проверка наличия audio данных
     */
    hasAudioData(audio) {
        if (!audio || typeof audio !== 'object') return false;
        const keys = ['pitch_mean', 'energy_mean', 'speaking_rate', 'voice_brightness'];
        return keys.some(key => audio[key] !== undefined && audio[key] !== null && audio[key] > 0);
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
     * Рендеринг общих метрик (компактно)
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
            'POSITIVE': '😊 Позитивный',
            'NEUTRAL': '😐 Нейтральный',
            'NEGATIVE': '😟 Негативный'
        };

        const intensityPercent = Math.round(intensity * 100);
        const intensityLabel = intensityPercent < 30 ? 'Спокойный' :
                              intensityPercent < 60 ? 'Умеренный' : 'Эмоциональный';

        return `
            <div class="overall-section">
                <div class="emotion-summary-row">
                    <div class="emotion-summary-item">
                        <span class="emotion-summary-label">Общий тон:</span>
                        <span class="emotion-badge emotion-badge-${overallColors[overall]}">
                            ${overallLabels[overall]}
                        </span>
                    </div>
                    <div class="emotion-summary-item">
                        <span class="emotion-summary-label">Интенсивность:</span>
                        <span class="emotion-badge emotion-badge-info">
                            ${intensityLabel} (${intensityPercent}%)
                        </span>
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
