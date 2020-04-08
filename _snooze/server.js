const bodyParser = require('body-parser');
const compression = require('compression');
const cors = require('cors');
const express = require('express');
const rp = require('request-promise');
const wakeWorker = require('./wake-worker');

Promise = require('bluebird');

const app = express();

// compress our client side content before sending it over the wire
app.use(compression());

// your manifest must have appropriate CORS headers, you could also use '*'
app.use(cors({ origin: '*' }));

// http://expressjs.com/en/starter/static-files.html
app.use(express.static('public'));

// helps us parse the body of POST requests to set snoozes
app.use(bodyParser.urlencoded({ extended: false }));

/*
// Keep Glitch from sleeping by periodically sending ourselves a http request
setInterval(function() {
  console.log('❤️ Keep Alive Heartbeat');
  rp('https://glitch.com/#!/project/trellocardsnooze')
  .then(() => {
    console.log('💗 Successfully sent http request to Glitch to stay awake.');
  })
  .catch((err) => {
    console.error(`💔 Error sending http request to Glitch to stay awake: ${err.message}`);
  });
}, 150000); // every 2.5 minutes
*/

// Start the wake cards heartbeat every 30 sec
wakeWorker.start(30000);

// Setup server routes
require('./routes.js')(app);

// listen for requests :)
const listener = app.listen(8081, () => {
  console.log('Card Snooze Server up and running 🏃');
});
