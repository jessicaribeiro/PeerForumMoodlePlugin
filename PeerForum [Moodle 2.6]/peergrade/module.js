M.core_peergrade = {
    Y : null,
    api: M.cfg.wwwroot + '/peergrade/peergrade_ajax.php',
    textfeedback: null,

    init : function(Y){
        this.Y = Y;
        Y.all('select.postpeergrademenu').each(this.attach_peergrade_events, this);

    },

    attach_peergrade_events : function(selectnode) {
        selectnode.on('change', this.submit_peergrade, this, selectnode);
    },

    submit_peergrade : function(e, selectnode){

        var theinputs = selectnode.ancestor('form').all('.peergradeinput');
        var thedata = [];

        var inputssize = theinputs.size();
        for (var i = 0; i < inputssize; i++) {
            if(theinputs.item(i).get("name") != "returnurl") { // Dont include return url for ajax requests.
                thedata[theinputs.item(i).get("name")] = theinputs.item(i).get("value");
            }
        }

        var scope = this;
        var cfg = {
            method: 'POST',
            on: {
                complete : function(tid, outcome, args) {
                    try {

                        if (!outcome) {
                            alert('IO FATAL');
                            return false;
                        }

                        var data = scope.Y.JSON.parse(outcome.responseText);

                        if (data.success){
                            //if the user has access to the aggregate then update it
                            if (data.itemid) { //do not test data.aggregate or data.count otherwise it doesn't refresh value=0 or no value
                                var itemid = data.itemid;

                                var node = scope.Y.one('#peergradeaggregate' + itemid);
                                node.set('innerHTML',data.aggregate);

                                // Empty the count value if no peergrades.
                                var node = scope.Y.one('#peergradecount' + itemid);
                                if (data.count > 0) {
                                    node.set('innerHTML', "(" + data.count + ")");
                                } else {
                                    node.set('innerHTML', "");
                                }

                            }
                            return true;
                        }
                        else if (data.error) {
                            alert(data.error);
                        }
                    } catch(e) {
                        alert(e.message + " " + outcome.responseText);
                    }
                    return false;
                }
            },
            arguments: {
                scope: scope
            },
            headers: {
            },
            data: build_querystring(thedata)
        };
        this.Y.io(this.api, cfg);
    }
};
