const db = require('./database');
const rp = require('request-promise');

Promise = require('bluebird');

let working = false;
let skippedHeartbeats = 0;
const maxSkips = 5;

// Helper to handle calling Trello to unarchive the card
const sendToBoard = Promise.coroutine(function* (token, idCard) {
  console.time(`Unarchiving ${idCard}`);
  let response;
  try {
    const unarchiveReq = {
      method: 'PUT',
      uri: `https://api.trello.com/1/cards/${idCard}/closed`,
      qs: {
        value: false,
        key: process.env.APP_KEY,
        token: token
      },
      json: true,
      timeout: 120000
    };
    response = yield rp(unarchiveReq);
  } catch (apiErr) {
    // handle non-retryable errors
    if (apiErr.statusCode === 404 || apiErr.statusCode === 401 || apiErr.statusCode === 403) {
      console.error(`Unable to wake up card. cardId=${idCard} error=${apiErr.message}`);
    } else {
      // this is a retriable error, so just finish up and don't
      // delete the item from the DB. It will be retried automatically
      // on the next heartbeat
      console.warn(`Unable to wake up card. Will Retry. cardId=${idCard} error=${apiErr.message}`);
      console.timeEnd(idCard);
      return;
    }
  }
  // we either successfully woke up the card, or hit a non-retriable error
  // we can go ahead and remove the item from our DB
  db.remove({ cardId: idCard }, {}, (err, numRemoved) => {
    if (err) {
      console.error(`Error removing snooze from DB. error=${err.message}`);
    } else {
      console.log(`Woke up card, and deleted snooze in DB. cardId=${idCard}`);
    }
  });
  console.timeEnd(`Unarchiving ${idCard}`);
});

// Find the cards that need to be unarchived, unarchive each and remove from database
const wakeUpCards = () => {
  console.log('😴 Wake Cards Heartbeat');
  if (working) {
    console.warn('Old interval still working, will skip this heartbeat.');
    skippedHeartbeats += 1;
    if (skippedHeartbeats >= maxSkips) {
      console.warn('Exceeded max wake heartbeat skips.');
      skippedHeartbeats = 0;
      working = false;
    }
    return;
  }
  working = true;
  const wakeUpTime = Math.floor(Date.now() / 1000) + 15;
  db.find({ snoozeTime: { $lte: wakeUpTime } }, (err, docs) => {
    if (err) {
      console.error();
      return;
    }
    console.time(`${wakeUpTime} Wake Heartbeat`);
    console.log(`Found ${docs.length} card(s) to be woken up.`);
    // we rate limit ourselves to 3 concurrent unarchive requests
    // this is to avoid hitting the 300 requests per 10 sec Trello rate limit
    // on average it takes ~100ms to complete an unarchive request, so 3 concurrent
    // should put us right around the limit
    Promise.map(docs, (doc) => sendToBoard(doc.token, doc.cardId), { concurrency: 3 })
    .then(() => {
      console.timeEnd(`${wakeUpTime} Wake Heartbeat`);
      working = false;
    });
  });
};

module.exports = {
  start: (interval) => setInterval(wakeUpCards, interval)
};
