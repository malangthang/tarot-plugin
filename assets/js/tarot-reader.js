jQuery(document).ready(function($) {
    'use strict';

    const TarotReader = {
        currentSpread: '3card',
        currentReading: null,
        isShuffling: false,

        init: function() {
            this.bindEvents();
            this.initializeSpreadSelection();
        },

        bindEvents: function() {
            // Spread selection
            $('.spread-option').on('click', this.selectSpread.bind(this));

            // Start reading
            $('#start-reading').on('click', this.startReading.bind(this));

            // Shuffle controls
            $('#shuffle-btn').on('click', this.shuffleCards.bind(this));
            $('#draw-cards-btn').on('click', this.drawCards.bind(this));

            // Reading actions
            $('#new-reading').on('click', this.newReading.bind(this));
            $('#save-reading').on('click', this.saveReading.bind(this));
        },

        initializeSpreadSelection: function() {
            $('.spread-option[data-spread="' + this.currentSpread + '"]').addClass('active');
        },

        selectSpread: function(e) {
            const spread = $(e.currentTarget).data('spread');
            this.currentSpread = spread;

            $('.spread-option').removeClass('active');
            $(e.currentTarget).addClass('active');
        },

        startReading: function() {
            const question = $('#tarot-question').val().trim();

            if (!question) {
                alert('Please enter your question before starting the reading.');
                return;
            }

            // Hide question section, show shuffle section
            $('#question-section').hide();
            $('#spread-selection').hide();
            $('#shuffle-section').show();

            this.startShuffleAnimation();
        },

        startShuffleAnimation: function() {
            this.isShuffling = true;
            const cards = $('.card-deck .card');
            let shuffleCount = 0;
            const maxShuffles = 20;

            const shuffleInterval = setInterval(() => {
                cards.each(function(index) {
                    const card = $(this);
                    const randomX = (Math.random() - 0.5) * 20;
                    const randomY = (Math.random() - 0.5) * 20;
                    const randomRotate = (Math.random() - 0.5) * 10;

                    card.css({
                        'transform': `translate(${randomX}px, ${randomY}px) rotate(${randomRotate}deg)`,
                        'transition': 'transform 0.3s ease'
                    });
                });

                shuffleCount++;
                if (shuffleCount >= maxShuffles) {
                    clearInterval(shuffleInterval);
                    this.isShuffling = false;
                    $('#shuffle-btn').hide();
                    $('#draw-cards-btn').show();

                    // Reset card positions
                    cards.css({
                        'transform': 'translate(0, 0) rotate(0deg)',
                        'transition': 'transform 0.5s ease'
                    });
                }
            }, 200);
        },

        shuffleCards: function() {
            if (this.isShuffling) return;
            this.startShuffleAnimation();
        },

        drawCards: function() {
            $('#loading-overlay').show();

            const question = $('#tarot-question').val();

            $.ajax({
                url: tarot_ajax.rest_url + 'reading',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    question: question,
                    spread_type: this.currentSpread
                }),
                success: (response) => {
                    this.currentReading = response;
                    this.displayReading(response);
                },
                error: (xhr, status, error) => {
                    console.error('Error creating reading:', error);
                    alert('Error creating reading. Please try again.');
                },
                complete: () => {
                    $('#loading-overlay').hide();
                }
            });
        },

        displayReading: function(reading) {
            $('#shuffle-section').hide();
            $('#reading-results').show();

            const cardsHtml = reading.cards.map((cardData, index) => {
                const card = cardData.card;
                const orientation = cardData.is_reversed ? 'reversed' : 'upright';
                const meaning = cardData.is_reversed ?
                    (cardData.meanings.reversed?.general?.meaning || 'No meaning available') :
                    (cardData.meanings.upright?.general?.meaning || 'No meaning available');

                return `
                    <div class="tarot-card-result" data-index="${index}">
                        <div class="card-position">${cardData.position}</div>
                        <div class="card-name">${card.name}</div>
                        <div class="card-orientation">${orientation}</div>
                        <div class="card-meaning">${meaning.substring(0, 150)}...</div>
                    </div>
                `;
            }).join('');

            $('#cards-display').html(cardsHtml);

            // Add flip animation to cards
            $('.tarot-card-result').on('click', this.flipCard.bind(this));

            // Generate AI interpretation
            this.generateInterpretation(reading);
        },

        flipCard: function(e) {
            const cardElement = $(e.currentTarget);
            const index = cardElement.data('index');
            const cardData = this.currentReading.cards[index];

            if (cardElement.hasClass('flipped')) {
                cardElement.removeClass('flipped');
                cardElement.html(`
                    <div class="card-position">${cardData.position}</div>
                    <div class="card-name">${cardData.card.name}</div>
                    <div class="card-orientation">${cardData.is_reversed ? 'reversed' : 'upright'}</div>
                    <div class="card-meaning">${(cardData.is_reversed ?
                        (cardData.meanings.reversed?.general?.meaning || 'No meaning available') :
                        (cardData.meanings.upright?.general?.meaning || 'No meaning available')).substring(0, 150)}...</div>
                `);
            } else {
                cardElement.addClass('flipped');
                const fullMeaning = cardData.is_reversed ?
                    (cardData.meanings.reversed?.general?.meaning || 'No meaning available') :
                    (cardData.meanings.upright?.general?.meaning || 'No meaning available');

                cardElement.html(`
                    <div class="card-position">${cardData.position}</div>
                    <div class="card-name">${cardData.card.name}</div>
                    <div class="card-orientation">${cardData.is_reversed ? 'reversed' : 'upright'}</div>
                    <div class="card-meaning">${fullMeaning}</div>
                `);
            }
        },

        generateInterpretation: function(reading) {
            $('#interpretation-content').html('<p>Generating interpretation...</p>');

            // For now, create a simple interpretation
            // In a real implementation, you might call an AI service
            const interpretation = this.createSimpleInterpretation(reading);
            $('#interpretation-content').html(`<p>${interpretation}</p>`);
        },

        createSimpleInterpretation: function(reading) {
            const cards = reading.cards;
            let interpretation = `Your question: "${reading.question}"<br><br>`;

            cards.forEach((cardData, index) => {
                const card = cardData.card;
                const position = cardData.position;
                const orientation = cardData.is_reversed ? 'reversed' : 'upright';

                interpretation += `<strong>${position}:</strong> ${card.name} (${orientation})<br>`;
                interpretation += `This suggests: ${cardData.is_reversed ?
                    (cardData.meanings.reversed?.general?.meaning || 'Focus on inner wisdom') :
                    (cardData.meanings.upright?.general?.meaning || 'New beginnings and opportunities')}<br><br>`;
            });

            interpretation += '<em>Remember, tarot readings are for guidance and reflection. Trust your intuition in interpreting these messages.</em>';

            return interpretation;
        },

        newReading: function() {
            this.currentReading = null;
            $('#reading-results').hide();
            $('#shuffle-section').hide();
            $('#spread-selection').show();
            $('#question-section').show();
            $('#tarot-question').val('');
            $('#draw-cards-btn').hide();
            $('#shuffle-btn').show();
        },

        saveReading: function() {
            if (!this.currentReading) return;

            // In a real implementation, you might save to user account
            alert('Reading saved! (This is a demo - in production this would save to your account)');

            // You could also provide a way to email the reading or save to localStorage
            const readingData = {
                question: this.currentReading.question,
                spread: this.currentReading.spread,
                cards: this.currentReading.cards,
                timestamp: new Date().toISOString()
            };

            localStorage.setItem('lastTarotReading', JSON.stringify(readingData));
        }
    };

    // Initialize the tarot reader
    TarotReader.init();

    // Legacy support for old shortcode
    $('#start').on('click', function() {
        const question = $('#q').val();
        if (!question) {
            alert('Please enter a question');
            return;
        }

        $.ajax({
            url: tarot_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'tarot_draw_3',
                question: question
            },
            success: function(response) {
                let html = '<h3>Your 3-Card Reading</h3>';
                response.forEach(function(card, index) {
                    const pos = ['Past', 'Present', 'Future'][index];
                    html += `<div class="card-result">
                        <h4>${pos}: ${card.card.name}</h4>
                        <p>${card.reversed ? 'Reversed' : 'Upright'}</p>
                    </div>`;
                });
                $('#result').html(html);
            }
        });
    });
});