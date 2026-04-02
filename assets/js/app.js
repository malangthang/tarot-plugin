jQuery(function($){

    $('#start').click(function(){

        let q = $('#q').val();

        $.post(tarot_ajax.ajax_url, {
            action:'tarot_draw_3'
        }, function(cards){

            $('#result').html('');

            cards.forEach(c => {
                $('#result').append(`
                    <div>
                        <h3>${c.card.name}</h3>
                        <p>${c.reversed ? c.card.meaning_reversed : c.card.meaning_upright}</p>
                    </div>
                `);
            });

            $.post(tarot_ajax.ajax_url, {
                action:'tarot_ai',
                question:q,
                cards:cards
            }, function(res){
                $('#ai').html(res.text);
            });

        });

    });

});