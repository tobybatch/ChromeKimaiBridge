/* global TrelloPowerUp, moment, Pikaday */

var Promise = TrelloPowerUp.Promise;
var t = TrelloPowerUp.iframe();
var now = moment().toDate();
var snoozeTime = null;
var token = null;

var TIME_FORMAT = 'LT';

t.get('member', 'private', 'token')
.then(function(storedToken) {
  token = storedToken;
});

var resize = function(){
  t.sizeTo('.dpicker-widget');
};

var picker = new Pikaday({
  bound: false,
  format: 'MM/DD/YYYY',
  defaultDate: now,
  setDefaultDate: now,
  minDate: now,
  container: document.getElementById('datepicker'),
  field: document.getElementById('date-input'),
  onDraw: function() {
    resize();
  }
});

t.get('card', 'shared', 'unixTime')
.then(function(unixTime) {
  if (unixTime) {
    // unhide remove button if card has a snooze time set
    document.getElementById('remove-btn').classList.remove('u-hidden');
    var existingMoment = new moment(unixTime * 1000);
    // set pikaday to match currently set snooze date
    picker.setMoment(existingMoment);
    document.getElementById('time-input').value = existingMoment.format(TIME_FORMAT);
  }
});

document.querySelector('#time-input').addEventListener('input', function(){
  var time = document.getElementById('time-input').value;
  time = moment(time, TIME_FORMAT).format(TIME_FORMAT);
  if (!moment(time, TIME_FORMAT).isValid()) {
    document.querySelector('#time-input').style.borderColor='#EC9488';
  } else {
    document.querySelector('#time-input').style.borderColor='#CDD2D4';
  }
});

document.getElementById('save-btn').addEventListener('click', function(){
  var displayDate = picker.getMoment().format('MM/DD/YYYY');
  var timeMoment = moment(document.getElementById('time-input').value, TIME_FORMAT);
  if (!timeMoment.isValid()) {
    timeMoment = moment('12:00 PM', TIME_FORMAT);
  }
  var snoozeTime =  displayDate + ', ' + timeMoment.format(TIME_FORMAT);
  var unixTime = picker.getMoment().hour(timeMoment.hour()).minute(timeMoment.minute()).unix();
  t.card('id')
  .then(function(card){
    $.post('/snooze?', { token: token, cardId: card.id, snoozeTime: unixTime }, function(){
      return t.set('card', 'shared', { idCard: card.id, time: snoozeTime, unixTime: unixTime })
      .then(function(){
        t.closePopup();
      });
    });
  })
  .catch(function(err){
    console.error(err);
  });
});

document.getElementById('remove-btn').addEventListener('click', function(){
  t.card('id')
  .then(function(card){
    $.ajax({
      url: `/snooze/${card.id}?` + $.param({ token: token }),
      type: 'DELETE',
      success: function(){
        return t.set('card', 'shared', { idCard: null, time: null, unixTime: null })
        .then(function(){
          t.closePopup();
        });
      },
      error: function(err){ console.error('Error deleting from server: ' + JSON.stringify(err)); }
    });
  })
  .catch(function(err){
    console.error('Error unarchiving card');
    console.error(err);
  });
});

t.render(function(){
  resize();
})