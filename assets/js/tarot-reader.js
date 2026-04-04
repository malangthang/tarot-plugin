document.addEventListener("DOMContentLoaded", () => {

    const state = {
        selected: [],
        cards: [],
        spread: "3card"
    };

    const el = {
        topic: document.querySelector(".tarot-topic"),
        stage: document.querySelector(".tarot-stage"),
        deck: document.getElementById("cardDeck"),
        btn: document.getElementById("btnResult"),
        actions: document.querySelector(".tarot-actions"),
        result: document.getElementById("tarotResult"),
        countdown: document.getElementById("countdown")
    };

    /* ======================
       STEP 1: CHỌN CHỦ ĐỀ
    ====================== */
    document.querySelectorAll(".topic").forEach(item => {
        item.onclick = () => {
            el.topic.style.display = "none";
            el.stage.classList.remove("hidden");
            startCountdown(initDeck);
        };
    });

    /* ======================
       COUNTDOWN
    ====================== */
    function startCountdown(cb) {
        let i = 3;
        el.countdown.innerText = i;

        let t = setInterval(() => {
            i--;
            el.countdown.innerText = i;

            if (i === 0) {
                clearInterval(t);
                el.countdown.style.display = "none";
                cb();
            }
        }, 500);
    }

    /* ======================
       RENDER + FAN
    ====================== */
    function initDeck() {
        el.deck.innerHTML = "";

        for (let i = 0; i < 22; i++) {
            let card = document.createElement("div");
            card.className = "tarot-card";
            card.dataset.index = i;

            card.innerHTML = `
                <div class="card">
                    <img src="https://tarotmienphi.com/tarot/video/card-back.jpg">
                </div>
            `;

            card.onclick = () => selectCard(card, i);
            el.deck.appendChild(card);
        }

        fanLayout();
    }

    /* ======================
       FAN LAYOUT
    ====================== */
    function fanLayout() {
        const cards = document.querySelectorAll(".tarot-card");

        cards.forEach((card, i) => {
            let angle = (i - 11) * 4;
            let radius = 250;

            let x = Math.sin(angle * Math.PI / 180) * radius;
            let y = Math.cos(angle * Math.PI / 180) * radius;

            card.style.transform = `translate(${x}px, ${y}px) rotate(${angle}deg)`;
        });
    }

    /* ======================
       CHỌN CARD
    ====================== */
    function selectCard(card, index) {
        if (state.selected.includes(index)) return;
        if (state.selected.length >= 3) return;

        state.selected.push(index);
        card.classList.add("selected");

        if (state.selected.length === 3) {
            el.actions.classList.remove("hidden");
        }
    }

    /* ======================
       DRAW API
    ====================== */
    el.btn.onclick = () => {

        fetch(ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "tarot_draw",
                spread_type: state.spread,
                nonce: tarot_ajax.nonce
            })
        })
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            state.cards = res.data.cards;
            reveal();
        });
    };

    /* ======================
       REVEAL (FLIP + MOVE)
    ====================== */
    function reveal() {

        const all = document.querySelectorAll(".tarot-card");

        const positions = [
            { x: -200, y: 0, r: -10 },
            { x: 0, y: -40, r: 0 },
            { x: 200, y: 0, r: 10 }
        ];

        state.selected.forEach((index, i) => {

            let elCard = all[index];
            let data = state.cards[i];

            // move to center first
            elCard.style.zIndex = 100 + i;
            elCard.style.transform = `translate(0,0) scale(1.2)`;

            setTimeout(() => {

                // flip + show
                elCard.innerHTML = `
                    <div class="card ${data.is_reversed ? 'rev' : ''}">
                        <img src="${data.card.image}">
                    </div>
                    <div class="name">${data.card.name}</div>
                `;

                // move to final position
                setTimeout(() => {
                    let p = positions[i];
                    elCard.style.transform = `
                        translate(${p.x}px, ${p.y}px)
                        rotate(${p.r}deg)
                    `;
                }, 300);

            }, i * 500);
        });

        setTimeout(loadResult, 2000);
    }

    /* ======================
       LOAD RESULT
    ====================== */
    function loadResult() {

        fetch("/wp-json/tarot/v1/reading", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                spread_type: state.spread,
                question: "auto",
                cards: state.cards
            })
        })
        .then(r => r.json())
        .then(res => {

            el.result.classList.remove("hidden");
            el.result.innerHTML = `
                <h3>Kết quả</h3>
                <div>${res.interpretation.summary || ''}</div>
            `;
        });
    }

});