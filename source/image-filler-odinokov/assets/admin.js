(function($){
    'use strict';

    var $wrap = $('.ifo-wrap');
    var $progress = $wrap.find('.ifo-progress');
    var $status = $wrap.find('.ifo-status');
    var $fill = $wrap.find('.ifo-bar-fill');
    var $log = $wrap.find('.ifo-log');
    var $stop = $wrap.find('.ifo-stop');
    var $scan = $wrap.find('.ifo-scan-result');
    var stopped = false;
    var running = false;

    function setStatus(text){ $status.text(text); }
    function appendLog(lines){
        if(!lines || !lines.length) return;
        $log.append(lines.join('\n') + '\n');
        $log.scrollTop($log[0].scrollHeight);
    }
    function setProgress(done, total){
        if(!total || total <= 0){ $fill.css('width','0%'); return; }
        var p = Math.min(100, Math.round(done * 100 / total));
        $fill.css('width', p + '%');
    }

    $wrap.on('click', '[data-action]', function(){
        var $btn = $(this);
        var act = $btn.data('action');

        if(act === 'scan_cats' || act === 'scan_products'){
            runOneStep({ do: act, offset: 0, fresh: 1 }, function(res){
                $scan.text(res.message || ('Готово: ' + res.total)).addClass('visible');
            });
            return;
        }

        if(running) return;
        running = true;
        stopped = false;
        $progress.prop('hidden', false);
        $log.text('');
        $scan.removeClass('visible').text('');
        setStatus(IFO.i18n.starting);
        $fill.css('width','0%');
        $wrap.find('[data-action]').prop('disabled', true);
        $stop.prop('disabled', false);

        var offset = 0;
        var total = 0;
        var processed = 0;
        var stepDelay = 600;
        var consecutiveEmpty = 0;
        var MAX_EMPTY_STEPS = 5;

        function loop(){
            if(stopped){
                finish(IFO.i18n.stopped, processed, total);
                return;
            }
            runOneStep({ do: act, offset: offset, fresh: (offset === 0 ? 1 : 0) }, function(res){
                if(res.is_scan){
                    finish(IFO.i18n.done, 0, 0);
                    return;
                }
                if(typeof res.total === 'number') total = res.total;
                if(typeof res.processed === 'number') processed += res.processed;
                appendLog(res.log);
                setStatus('Шаг: обработано ' + processed + ' из ~' + total);
                setProgress(processed, total);

                if(res.done){
                    finish(IFO.i18n.done, processed, total);
                    return;
                }
                if(!res.processed || res.processed === 0){
                    consecutiveEmpty++;
                    if(consecutiveEmpty >= MAX_EMPTY_STEPS){
                        finish('Остановлено: подряд ' + MAX_EMPTY_STEPS + ' пустых шагов (offset=' + res.next + ', total=' + total + '). Возможно, категорий больше нет или лимит хостера. Попробуйте перезапустить.', processed, total);
                        return;
                    }
                } else {
                    consecutiveEmpty = 0;
                }
                offset = res.next;
                setTimeout(loop, stepDelay);
            }, function(xhr){
                var msg = IFO.i18n.error;
                if(xhr && xhr.status){
                    msg += ' (HTTP ' + xhr.status + ')';
                }
                finish(msg, processed, total);
            });
        }
        loop();
    });

    $stop.on('click', function(){
        stopped = true;
        $stop.prop('disabled', true);
    });

    function finish(text, processed, total){
        running = false;
        setStatus(text + ' (обработано: ' + processed + (total ? (' / ' + total) : '') + ')');
        $fill.css('width','100%');
        $wrap.find('[data-action]').prop('disabled', false);
        $stop.prop('disabled', true);
    }

    function runOneStep(data, onOk, onErr){
        data.nonce = IFO.nonce;
        data.action = 'ifo_run';
        $.post(IFO.ajaxUrl, data)
            .done(function(r){
                if(r && r.success && r.data){ onOk(r.data); }
                else { onErr && onErr(r); }
            })
            .fail(function(){ onErr && onErr(); });
    }
})(jQuery);
