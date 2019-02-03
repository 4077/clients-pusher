// head {
var __nodeId__ = "clients_pusher__main";
// }

(function (__nodeId__) {
    window[__nodeId__] = {

        pusher: null,

        channels: [],

        subscribe: function (data) {
            var node = window[__nodeId__];

            if (ewma.log.pusher) {
                Pusher.logToConsole = true;
            }

            if (node.pusher === null) {
                node.pusher = new Pusher(data.key, {
                    encrypted: true,
                    cluster:   data.cluster
                });
            }

            node.self = data.self;

            if (node.channels[data.channel] === undefined) {
                node.channels[data.channel] = node.pusher.subscribe(data.channel);
            }

            node.channels[data.channel].unbind('trigger');
            node.channels[data.channel].bind('trigger', function (data) {
                if ((!data.self || data.self === node.self) && (data.tab !== ewma.appData.tab)) {
                    ewma.trigger(data.event, data.data);
                }
            });
        }
    }
})(__nodeId__);
