// we can access Bluebird Promises as follows
window.Promise = TrelloPowerUp.Promise;

TrelloPowerUp.initialize({
    'card-buttons': function(t, opts) {
        return t.get('member', 'private', 'token')
            .then(function(token){
                return [{
                    // icon: 'https://www.kimai.org/assets/icon/apple-touch-icon.png',
                    text: 'Kimai',
                    callback: function(context) {
                        /*
                        if (!token) {
                            context.popup({
                                title: 'Authorize Your Account',
                                url: './auth.html',
                                height: 75
                            });
                        } else {
                            return context.popup({
                                title: 'Change Snooze Time',
                                url: './set-snooze.html',
                                height: 411
                            });
                        }
                        */
                        return context.popup({
                            title: 'Log Time',
                            url: './trello/logtime',
                            height: 411
                        });
                    }
                }];
            });
    }
});
