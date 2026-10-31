/* Tasty Bites - small client-side enhancements */
document.addEventListener('DOMContentLoaded', function () {

  // quantity steppers on menu cards
  document.querySelectorAll('.js-qty').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.parentElement.querySelector('.input-qty');
      if (!input) return;
      var v = parseInt(input.value || '1', 10) + (btn.dataset.dir === '1' ? 1 : -1);
      input.value = Math.max(1, Math.min(20, v));
    });
  });

  // auto-hide flash messages after 4s
  document.querySelectorAll('.flash').forEach(function (f) {
    setTimeout(function () {
      f.style.transition = 'opacity .5s';
      f.style.opacity = '0';
      setTimeout(function () { f.remove(); }, 500);
    }, 4000);
  });

  // ---------- checkout: minute-level pickup time picker ----------
  var timeInput = document.getElementById('pickup-time');
  if (timeInput && window.TB_PICKUP) {
    var hint     = document.getElementById('time-hint');
    var prevTime = document.getElementById('prev-time');
    var cfg      = window.TB_PICKUP;

    function toMin(hhmm) {
      var p = hhmm.split(':');
      return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
    }
    function pretty(hhmm) {
      var p = hhmm.split(':'), h = parseInt(p[0], 10), m = p[1];
      var am = h < 12 ? 'AM' : 'PM', h12 = h % 12; if (h12 === 0) h12 = 12;
      return h12 + ':' + m + ' ' + am;
    }

    function validate() {
      var v = timeInput.value;
      if (prevTime) prevTime.textContent = v ? pretty(v) : '\u2014';
      if (!v) { setHint('', ''); return; }
      var t = toMin(v), open = toMin(cfg.open), close = toMin(cfg.close);
      if (t < open || t > close) {
        setHint('Kitchen is open ' + pretty(cfg.open) + ' \u2013 ' + pretty(cfg.close) + '. Pick a time inside these hours.', 'bad');
        return;
      }
      if (cfg.isToday) {
        var now = new Date();
        var nowMin = now.getHours() * 60 + now.getMinutes();
        if (t < nowMin) {
          setHint('That minute already passed today \u2013 pick a later time or another day.', 'bad');
          return;
        }
        if (t < nowMin + 15) {
          setHint('Need at least 15 minutes from now \u2013 earliest today: ' + pretty(minutesToHHMM(nowMin + 15)) + '.', 'bad');
          return;
        }
      }
      setHint('\u2713 Great \u2013 pickup at ' + pretty(v) + (cfg.isToday ? ' today' : '') + '.', 'ok');
    }

    function minutesToHHMM(mins) {
      mins = Math.min(mins, 23 * 60 + 59);
      var h = Math.floor(mins / 60), m = mins % 60;
      return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
    }

    function setHint(msg, cls) {
      if (!hint) return;
      hint.textContent = msg;
      hint.className = 'time-hint muted small' + (cls ? ' time-' + cls : '');
    }

    timeInput.addEventListener('input', validate);
    timeInput.addEventListener('change', validate);

    // quick time chips
    document.querySelectorAll('.qt-chip').forEach(function (chip) {
      chip.addEventListener('click', function () {
        if (!chip.dataset.time) return;
        timeInput.value = chip.dataset.time;
        document.querySelectorAll('.qt-chip').forEach(function (c) { c.classList.remove('qt-active'); });
        chip.classList.add('qt-active');
        timeInput.dispatchEvent(new Event('change'));
      });
    });

    validate();
  }
});
