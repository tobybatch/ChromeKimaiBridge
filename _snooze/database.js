const _ = require('lodash');
const Datastore = require('nedb');

// Security note: the database is saved to the file `datafile` on the local filesystem. It's deliberately placed in the `.data` directory
// which doesn't get copied if someone remixes the project.
const db = new Datastore({ filename: '.data/datafile', autoload: true });

db.ensureIndex({ fieldName: 'cardId', unique: true }, function (err) {
  if (err) {
    console.error('Error setting up index on cardId');
    console.error(err.message);
  } else {
    console.log('🛠 Built unique index on cardId');
  }
});

db.ensureIndex({ fieldName: 'snoozeTime' }, function (err) {
  if (err) {
    console.error('Error setting up index on snoozeTime');
    console.error(err.message);
  } else {
    console.log('🛠 Built index on snoozeTime');
  }
});

db.count({}, (err, count) => {
  if (err) {
    console.error(err);
  } else {
    console.info(`DB currently has ${count} snoozed cards`);
  }
});

module.exports = db;