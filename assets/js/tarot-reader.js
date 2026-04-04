jQuery(document).ready(function($) {
    'use strict';

    const TarotReader = {
        currentSpread: '3card',
        currentReading: null,
        selectedCards: [], // Track drawn cards
        isLoading: false, // Prevent multiple requests
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

            // Reset state
            this.selectedCards = [];
            this.currentReading = null;

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
            if (this.isShuffling || this.isLoading) return;
            this.startShuffleAnimation();
        },

        drawCards: function() {
            if (this.isLoading) return;

            this.isLoading = true;
            $('#loading-overlay').show();

            const question = $('#tarot-question').val();

            // Step 1: Draw cards via AJAX
            $.ajax({
                url: tarot_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'tarot_draw',
                    spread_type: this.currentSpread,
                    nonce: tarot_ajax.nonce
                },
                success: (drawResponse) => {
                    if (drawResponse.success) {
                        this.selectedCards = drawResponse.data.cards;

                        // Step 2: Interpret cards via AJAX
                        this.interpretCards(question);
                    } else {
                        alert('Error drawing cards. Please try again.');
                        this.isLoading = false;
                        $('#loading-overlay').hide();
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error drawing cards:', error);
                    alert('Error drawing cards. Please try again.');
                    this.isLoading = false;
                    $('#loading-overlay').hide();
                }
            });
        },

        interpretCards: function(question) {
            $.ajax({
                url: tarot_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'tarot_interpret',
                    cards: this.selectedCards,
                    spread_type: this.currentSpread,
                    question: question,
                    nonce: tarot_ajax.nonce
                },
                success: (interpretResponse) => {
                    this.isLoading = false;
                    $('#loading-overlay').hide();

                    if (interpretResponse.success) {
                        const readingData = {
                            question: question,
                            spread_type: this.currentSpread,
                            cards: this.selectedCards,
                            interpretation: interpretResponse.data.interpretation,
                            cache_key: interpretResponse.data.cache_key
                        };

                        this.currentReading = readingData;
                        this.displayReading(readingData);
                    } else {
                        alert('Error interpreting cards. Please try again.');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error interpreting cards:', error);
                    alert('Error interpreting cards. Please try again.');
                    this.isLoading = false;
                    $('#loading-overlay').hide();
                }
            });
        },

        displayReading: function(reading) {
            $('#shuffle-section').hide();
            $('#reading-results').show();

            const interp = reading.interpretation;

            // Display title and question
            let html = '<div class="reading-header">';
            html += '<h3>' + interp.title + '</h3>';
            html += '<div class="reading-question">' + interp.summary + '</div>';
            html += '</div>';

            // Display main interpretation
            html += '<div class="main-interpretation">';
            html += '<h4>Your Reading</h4>';
            html += '<p class="interpretation-text">' + interp.interpretation + '</p>';

            // Show answer for single card
            if (interp.answer) {
                html += '<div class="yes-no-answer"><strong>Answer: ' + interp.answer + '</strong></div>';
            }

            html += '</div>';

            // Display individual cards in collapsible section
            html += '<div class="cards-detail-section">';
            html += '<h4 class="toggle-cards">📖 Click to see individual cards</h4>';
            html += '<div class="cards-display" style="display:none;">';

            interp.cards_display.forEach((card_data, index) => {
                html += '<div class="tarot-card-result">';
                html += '<div class="card-header">';
                html += '<strong class="card-position">' + card_data.position + '</strong>';
                html += '<span class="card-orientation-badge">' + card_data.orientation + '</span>';
                html += '</div>';
                html += '<div class="card-name">' + card_data.card_name + '</div>';
                html += '<div class="card-meaning">' + this.truncate(card_data.meaning, 150) + '</div>';
                html += '</div>';
            });

            html += '</div>';
            html += '</div>';

            $('#reading-results').html(html);

            // Toggle cards display
            $('.toggle-cards').on('click', function() {
                $(this).next('.cards-display').slideToggle(300);
                $(this).toggleClass('expanded');
            });
        },

        truncate: function(text, length) {
            if (text.length <= length) return text;
            return text.substring(0, length) + '...';
        },

        newReading: function() {
            this.currentReading = null;
            this.selectedCards = [];
            $('#reading-results').hide();
            $('#shuffle-section').hide();
            $('#spread-selection').show();
            $('#question-section').show();
            $('#tarot-question').val('');
            $('#draw-cards-btn').hide();
            $('#shuffle-btn').show();

            // Reset card positions
            const cards = $('.card-deck .card');
            cards.css({
                'transform': 'translate(0, 0) rotate(0deg)',
                'transition': 'transform 0.5s ease'
            });
        },

        saveReading: function() {
            if (!this.currentReading) return;

            alert('Reading saved! (In production this would save to your account)');

            const readingData = {
                question: this.currentReading.question,
                spread: this.currentReading.spread_type,
                cards: this.currentReading.cards,
                timestamp: new Date().toISOString(),
                cache_key: this.currentReading.cache_key
            };

            localStorage.setItem('lastTarotReading', JSON.stringify(readingData));
        }
    };

    // Initialize the tarot reader
    TarotReader.init();
});