(function () {
    App.blink=function ($element, $times, $color) {
        let i = $times || 8;
        let warn = true;
        let blink = function () {
            if (warn){
                $element.css('background-color', $color);
            }else{
                $element.css('background-color', '');
            }
            warn = !warn;
            i--;
            if (i>0){
                setTimeout(blink, 300)
            }
        };
        setTimeout(blink, 300)
    }

})();
(function () {
    App.panelTimer=function(title, numStart, func){
        let i=numStart;
        let $div = $('<div>');
        $div.html(i);
        let TimeObject;

        let panelTimer = BootstrapDialog.show({
            type: BootstrapDialog.TYPE_DANGER,
            title:title ,
            message: $div,
            buttons: [
                {
                    action: function (panelTimer) {
                        if (TimeObject){
                            clearTimeout(TimeObject);
                        }
                        panelTimer.close();
                    },
                    label: 'Отмена'
                }]
        });
        let timeoutFunc = function () {
            if (--i<=0){
                panelTimer.close();
                func();
            }else{
                $div.html(i);
                TimeObject=setTimeout(timeoutFunc, 1000);
            }
        };
        timeoutFunc();
    }

})();
(function () {
    App.setSessionStorage=function (name, data) {
        sessionStorage.setItem(name, JSON.stringify(data))
    }

})();
(function () {
    BootstrapDialog.BUTTON_SIZES[BootstrapDialog.SIZE_NORMAL] = 'btn-m';
    BootstrapDialog.defaultOptions.animate=false;
    BootstrapDialog.defaultOptions.closeByBackdrop=false;

})();
(function () {
    let iframeNum = 0;

    App.showLInks = function (links, model) {
        links.forEach(function (linkObject) {

            if (linkObject.target === 'top-iframe' && window.top!=window){
                window.top.App.showLInks([linkObject], model);
                return;
            }

            let openLinkLocation = function (target) {
                "use strict";

                if (linkObject.postData) {
                    let _target = '_self';
                    let form;

                    if (target === 'iframe' || target === 'top-iframe') {
                        let iframeName = 'iframe' + (++iframeNum);
                        _target = iframeName;

                        let fn = BootstrapDialog;

                        let $iframe;

                        let dialog=fn.show({
                            message: $iframe = $('<iframe style="width: 100%; ' + (linkObject.width ? '' : 'min-width: 500px;') + ' height: 70vh; border: none" name = "' + iframeName + '"></iframe>'),
                            size: BootstrapDialog.SIZE_WIDE,
                            title: linkObject['title'],
                            draggable: true,
                            cssClass: 'target-iframe',
                            onhidden: function () {
                                if (linkObject.refresh) {
                                    let pcTable = $('#table').data('pctable');
                                    model.refresh()
                                    //window.location.reload();
                                }
                            },
                            onshown: function (dialog) {
                                if (linkObject.width) {
                                    let width = 500;
                                    if (linkObject.width > width) width = linkObject.width;
                                    dialog.$modalDialog.width(width)
                                }
                                let wnd = $iframe.get(0).contentWindow;
                                let check = function () {
                                    if (wnd.App && wnd.App.setSessionStorage) wnd.App.setSessionStorage.call(wnd, 'linkObject', linkObject);
                                    else {
                                        setTimeout(check, 200)
                                    }
                                };
                                check();
                            },
                            buttons: [
                                {
                                    'label': "Обновить",
                                    cssClass: 'btn-m btn-default',
                                    'action': function () {
                                        $iframe.get(0).contentWindow.location.reload();
                                        form.detach();
                                    }
                                },
                                {
                                    'label': "Открыть",
                                    cssClass: 'btn-m btn-default',
                                    'action': function (dialog) {
                                        openLinkLocation('self');
                                        //dialog.close();
                                    }
                                },
                                {
                                    'label': "Открыть в новой вкладке",
                                    cssClass: 'btn-m btn-default',
                                    'action': function (dialog) {
                                        if ($iframe.get(0).contentWindow.sessionStorage.linkObject)
                                            linkObject = JSON.parse($iframe.get(0).contentWindow.sessionStorage.linkObject);

                                        openLinkLocation('blank');
                                        dialog.close();
                                    }
                                },
                                {
                                    'label': "Закрыть",
                                    cssClass: 'btn-m btn-default',
                                    'action': function (dialog) {
                                        dialog.close();
                                    }
                                }
                            ]
                        });

                        $iframe.on('load', function () {
                            let _window = $iframe.get(0).contentWindow;
                            _window.closeMe = function () {
                                dialog.close();
                            };
                        })
                    } else if (target === 'blank') {
                        _target = '_blank';
                    } else if (target === 'parent') {
                        _target = '_parent';
                        //window.parent.App.linkObject=linkObject;;
                    } else if (target === 'top') {
                        _target = '_top';
                    } else {

                        if (window.parent !== window) {
                            sessionStorage.linkObject = JSON.stringify(linkObject);
                        }
                    }

                    form = $('<form>', {
                        method: "post",
                        action: linkObject.uri,
                        target: _target,
                    });


                    const getMultiInput = function (name, v) {
                        if (typeof v === 'boolean') {
                            v = v === true ? 'true' : 'false';
                        }
                        if (typeof v === 'string' || typeof v === 'number' || v === null) {
                            form.append($('<input>', {
                                type: 'hidden',
                                name: name,
                                value: v
                            }))
                        } else {
                            $.each(v, function (k, v) {
                                getMultiInput(name + '[' + k + ']', v);
                            });
                        }
                    };

                    $.each(linkObject.postData, function (k, v) {
                        getMultiInput(k, v);
                    });
                    form.appendTo('body').submit();
                    form.detach();

                } else {
                    switch (target) {
                        case 'top':
                            window.top.location.href = linkObject.uri;
                            break;
                        case 'parent':
                            //window.parent.App.linkObject=linkObject;;
                            window.parent.location.href = linkObject.uri;
                            break;
                        case 'blank':
                            let a = $('<a href="' + linkObject.uri + '" target="_blank">link</a>');
                            a.appendTo('body');
                            a.get(0).click();
                            a.remove();
                            break;
                        case 'iframe':
                        case 'top-iframe':
                            let uri = linkObject.uri;
                            if (linkObject.elseData) {
                                let withoutCategories = [];
                                if (linkObject.elseData.header === false) {
                                    withoutCategories.push('param')
                                }
                                if (linkObject.elseData.footer === false) {
                                    withoutCategories.push('footer')
                                }
                                uri += '#' + encodeURIComponent(JSON.stringify({wc: withoutCategories}));
                            }
                            let $iframe = $('<iframe src="' + uri + '" style="width: 100%; height: 70vh; border: none"></iframe>');

                            let fn = BootstrapDialog;

                            let dialog = fn.show({
                                message: $iframe,
                                draggable: true,
                                size: BootstrapDialog.SIZE_WIDE,
                                title: linkObject['title'],
                                cssClass: 'target-iframe',
                                onhidden: function () {
                                    if (linkObject.refresh) {
                                        model.refresh()
                                    }
                                },
                                onshown: function (dialog) {
                                    if (linkObject.width) {
                                        dialog.$modalDialog.width(linkObject.width)
                                    }
                                    let wnd = $iframe.get(0).contentWindow;
                                    let check = function () {
                                        if (wnd.App && wnd.App.setSessionStorage) wnd.App.setSessionStorage.call(wnd, 'linkObject', linkObject);
                                        else {
                                            setTimeout(check, 200)
                                        }
                                    };
                                    check();
                                },
                                buttons: [
                                    {
                                        'label': "Обновить",
                                        cssClass: 'btn-m btn-default',
                                        'action': function () {
                                            $iframe.get(0).contentWindow.location.reload();
                                        }
                                    },
                                    {
                                        'label': "Открыть",
                                        cssClass: 'btn-m btn-default',
                                        'action': function (dialog) {
                                            if ($iframe.get(0).contentWindow.sessionStorage.linkObject)
                                                linkObject = JSON.parse($iframe.get(0).contentWindow.sessionStorage.linkObject);
                                            openLinkLocation('self');
                                            //dialog.close();
                                        }
                                    },
                                    {
                                        'label': "Открыть в новой вкладке",
                                        cssClass: 'btn-m btn-default',
                                        'action': function (dialog) {

                                            if ($iframe.get(0).contentWindow.sessionStorage.linkObject)
                                                linkObject = JSON.parse($iframe.get(0).contentWindow.sessionStorage.linkObject);

                                            openLinkLocation('blank');
                                            dialog.close();
                                        }
                                    },
                                    {
                                        'label': "Закрыть",
                                        cssClass: 'btn-m btn-default',
                                        'action': function (dialog) {
                                            dialog.close();
                                        }
                                    }
                                ]
                            });
                            $iframe.on('load', function () {
                                let _window = $iframe.get(0).contentWindow;
                                _window.closeMe = function () {
                                    dialog.close();
                                };
                            })
                            break;
                        default:
                            if (window.parent != window) {
                                sessionStorage.linkObject = JSON.stringify(linkObject);
                            }
                            window.location.href = linkObject.uri;

                    }
                }

            };
            openLinkLocation(linkObject.target);

        });
    };
    App.showDatas = function (datas, notificationId) {
        let dialogs = [];
        let model = this;
        datas.forEach(function (data) {
            switch (data[0]) {
                case 'table':
                    dialogs.push(showTable(data[1], model));
                    break;
                case 'text':
                    dialogs.push(showText(data[1], model));
                    break;
                case 'print':
                    dialogs.push(showPrint(data[1]['body'], data[1]['styles']));
                    break;
                case 'notification':
                    if (data[1].text) {
                        let notification = $.notify({
                            message: '<div>' + data[1].text + '</div>'
                        }, {
                            'offset': {x: 20, y: 50},
                            type: 'warning',
                            allow_dismiss: true,
                            delay: 0,
                            onClose: function () {
                                model.notificationUpdate(notificationId, 'deactivate').then(function () {
                                    notification.$ele.trigger('hide.bs.modal');
                                });
                            }
                        });
                        notification.$ele.find('button.close').before('<button class="timer"><i class="fa fa-clock-o"></i></button>');
                        notification.$ele.on('click', '.timer', function () {
                            model.notificationUpdate(notificationId, 'later').then(function () {
                                notification.$ele.trigger('hide.bs.modal');
                                notification.$ele.remove();
                            })
                        });
                        dialogs.push({
                            $modal: notification.$ele, simpleClose: function () {
                                notification.$ele.remove();
                            }
                        });

                    } else {

                        dialogs.push(showNotificationTable(data[1]));
                    }
                    break;
            }
        });
        return dialogs;
    };
    App.getPcTableById = function (id, elseData, element, config_else) {
        let $d = $.Deferred();
        (new App.models.table('/Table/0/' + id.toString(), {}, {})).getTableData(elseData ? elseData.sess_hash : null).then(function (config) {

            if (config_else && (config_else.withHeader === false || config_else.withFooter === false)) {
                let fields = [];
                Object.values(config.fields).forEach(function (field, i) {
                    if (field.category === 'param' && config_else.withHeader === false) delete config.fields[field.name];
                    else if (field.category === 'footer' && config_else.withFooter === false) delete config.fields[field.name];
                });
                delete config_else.withHeader;
                delete config_else.withFooter;
            }

            config.model = new App.models.table('/Table/0/' + id.toString(), $.extend({'updated': config.updated}, elseData || {}));

            $.extend(true, config, config_else);

            let pcTable = new App.pcTableMain(element, config);
            $d.resolve(pcTable);
        });
        return $d.promise();
    };
    App.showPanels = function (panels) {
        if (window.top != window) return window.top.App.showPanels.call(window.top, panels)

        let pcTables = {};
        let def = $.Deferred();
        const showPanel = function () {
            let panel = panels.shift();

            let data = {};
            if (panel.id) {
                data.id = panel.id;
            } else if (panel.field) {
                data = panel.field;
            }

            const show = function (pcTable) {
                (new EditPanel(pcTable.tableRow.id, BootstrapDialog.TYPE_PRIMARY, data, panels.length > 0)).then(function (json, isNext) {
                    if (json && panel.refresh) {
                        let pcTable = $('#table').data('pctable');
                        pcTable.model.refresh()
                    } else if (json || isNext) {
                        if (panels.length) {
                            showPanel();
                            return;
                        }
                    }
                    def.resolve();
                });
            };

            if (panel.uri !== window.location.pathname) {
                if (pcTables[panel.uri]) {
                    show(pcTables[panel.uri]);
                } else {
                    (new App.models.table(panel.uri, {}, {})).getTableData().then(function (config) {
                        config.model = new App.models.table(panel.uri, {'updated': config.updated});
                        pcTables[panel.uri] = new App.pcTableMain(null, config);

                        show(pcTables[panel.uri]);
                    });
                }
            } else {
                show($('#table').data('pctable'));
            }
        };

        showPanel();
        return def;
    };

    let notificationDialog = function (title, body, width, refresh, type) {
        return BootstrapDialog.show({
            message: body,
            draggable: false,
            closable: false,
            modal: false,
            onhidden: function () {
                if (refresh) {
                    let pcTable = $('#table').data('pctable');
                    pcTable.model.refresh()
                }
            },
            onshown: function (dialog) {
                dialog.$modalHeader.remove();
                dialog.$modal.css({
                    position: 'static'
                });
                dialog.$modalDialog.css({
                    width: width || '600',
                    right: 0,
                    margin: 0,
                    position: 'fixed',
                    height: 300,
                    top: 0
                });
                dialog.$modalBody.css({
                    padding: 0
                });
                dialog.$modal.data('bs.modal').$backdrop.hide()
            }
        });
    };
    let dialog = function (title, body, width, refresh, type, model, btns) {

        btns = btns || [];
        btns.push({
            'label': "Закрыть",
            cssClass: 'btn-m btn-default',
            'action': function (dialog) {
                dialog.close();
            }
        });

        return BootstrapDialog.show({
            message: body,
            title: title,
            type: type || null,
            draggable: true,
            onhidden: function () {
                if (refresh) {
                    if (refresh === 'strong') {
                        window.location.reload()
                    } else {
                        model.refresh()
                    }
                }
            },
            onshown: function (dialog) {
                if (width) {
                    dialog.$modalDialog.width(width)
                }
            },
            buttons: btns
        });
    };

    function showText(data, model) {
        dialog(data['title'], data['text'], data.width, data.refresh, null, model)
    }

    function showNotificationTable(data) {

        if (data.table_id) {
            let $iframe = $('<iframe style="width: 100%; height: 100%; border: none;" src="/html.html?rand=' + Math.round(Math.random() * 10000) + '">');
            let div = $('<div class="notificationTable"></div>').appendTo('body').append($iframe).css({
                width: data.width,
                height: data.height
            });
            $iframe.on('load', function () {
                let _window = $iframe.get(0).contentWindow;

                data.ROLESLIST = window.ROLESLIST || $('#table').data('pctable').ROLESLIST;

                _window.data = data;
                _window.closeMe = function () {
                    div.trigger('hide.bs.modal');
                    div.remove();
                };
                $iframe.contents().find('body').append('<div class="page_content tree-minifyed"><div id="table"></div></div><script>let table_id=window.data.table_id;' +
                    ' App.getPcTableById(table_id, {sess_hash: window.data.sess_hash}, ' +
                    '$("#table"), {beforeSpaceHide: true, ROLESLIST: window.data.ROLESLIST, withoutScrolls: true, data: window.data.data, f: window.data.f, data_params: window.data.data_params, withHeader: !(window.data.elseData && window.data.elseData.header===false), withFooter: !(window.data.elseData && window.data.elseData.footer===false)})</script>');
            });

            return {
                $modal: div, simpleClose: function () {
                    div.remove();
                }
            };
        }
    }

    function showTable(data, model) {

        let height;
        if (data.height) {
            height = data.height;
            if (/^\d+$/.test(data.height)) {
                height += 'px';
            }
        }

        let $iframe = $('<iframe style="width: 100%; height: ' + (height || "80vh") + '; border: none;" src="/html.html?rand=' + Math.round(Math.random() * 10000) + '">');
        $('body').append($iframe);
        let btns = [];
        btns.push({
            'label': "В новой вкладке",
            cssClass: 'btn-m btn-default',
            'action': function (dialog) {

                let wnd = window.open('/Table/0/' + data.table_id + '?sess_hash=' + data.sess_hash, '_blank');
                dialog.close();

                return;


                //TODO задел для анонимной таблицы
                $.ajax('/html.html?rand=' + Math.round(Math.random() * 10000)).then(function (html) {
                    let wnd = window.open('', '_blank');
                    data.ROLESLIST = window.ROLESLIST || $('#table').data('pctable').ROLESLIST;
                    wnd.data = data;

                    wnd.document.write(html.replace(/<\/body>/, '<div id="TOTUM_FOOTER"><div>ВРЕМЕННАЯ ТАБЛИЦА</div></div><div class="page_content"><div id="table"></div></div><script>' +
                        ' App.getPcTableById(' + data.table_id + ', {sess_hash: "' + data.sess_hash + '"}, ' +
                        '$("#table"), {data: window.data.data,   ROLESLIST: window.data.ROLESLIST, f: window.data.f, data_params: window.data.data_params, withHeader: true, withFooter: true})</script></body>'));

                });

            }
        })

        let _dialog = dialog(data['title'], $iframe, data.width, data.refresh, BootstrapDialog.TYPE_SUCCESS, model, btns);
        $iframe.on('load', function () {
            let _window = $iframe.get(0).contentWindow;
            data.ROLESLIST = window.ROLESLIST || $('#table').data('pctable').ROLESLIST;
            _window.data = data;
            _window.closeMe = function () {
                _dialog.close();
            };
            $iframe.contents().find('body').append('<div class="page_content tree-minifyed"><div id="table"></div></div><script>let table_id=window.data.table_id;' +
                ' App.getPcTableById(table_id, {sess_hash: window.data.sess_hash}, ' +
                '$("#table"), {data: window.data.data,  ROLESLIST: window.data.ROLESLIST, f: window.data.f, data_params: window.data.data_params, withHeader: !(window.data.elseData && window.data.elseData.header===false), withFooter: !(window.data.elseData && window.data.elseData.footer===false)})</script>');
        })
    }

    function showPrint(body, styles, pdf) {
        App.fullScreenProcesses.showCog();


        if (pdf) {
            var pdfFile = new Blob([atob(pdf)], {
                type: "application/pdf"
            });
            var pdfUrl = URL.createObjectURL(pdfFile);
            var a = document.createElement('a');
            a.href = pdfUrl;
            a.target = '_blank';
            document.body.appendChild(a);
            a.click();

        } else {
            let iframe = $('<iframe style="width: 500px; height: 200px; position: absolute; top: -1000px; background: #fff;">').appendTo('body');
            let tempFrame = iframe[0];
            let tempFrameWindow = tempFrame.contentWindow ? tempFrame.contentWindow : tempFrame.contentDocument.defaultView;


            tempFrameWindow.document.head.innerHTML = '<style>' + styles + '</style>';
            tempFrameWindow.document.body.innerHTML = body;
            let iBody = tempFrameWindow.document.body;

            let def = $.Deferred();
            let iCheck = 0;
            const checkScroll = function () {
                iBody.scrollTop = iBody.scrollHeight + 100;
                if (++iCheck < 100 && iBody.scrollTop >= iBody.scrollHeight) {
                    setTimeout(checkScroll, 50);
                } else {
                    setTimeout(function () {
                        def.resolve();

                    }, 3000)
                }
            };

            const checkBodyHeight = function () {
                if (++iCheck < 100 && iBody.scrollHeight < 200) {
                    setTimeout(checkBodyHeight, 50);
                    return;
                }
                //console.log('checkBodyHeight'+iCheck);
                iCheck = 0;
                checkScroll();
            };
            checkBodyHeight();
            def.then(function () {
                App.fullScreenProcesses.hideCog();

                setTimeout(function () {
                    tempFrameWindow.focus();
                    tempFrameWindow.print();
                }, 250);

                setTimeout(function () {
                    // iframe.remove();
                }, 10000);
            });
        }
    }
})();
(function () {
    String.prototype.hashCode = function() {
        var hash = 0, i, chr;
        if (this.length === 0) return hash;
        for (i = 0; i < this.length; i++) {
            chr   = this.charCodeAt(i);
            hash  = ((hash << 5) - hash) + chr;
            hash |= 0; // Convert to 32bit integer
        }
        return hash;
    };

})();
(function () {

    App.confirmation = function (text, btns, title) {
        let _btns = [];
        Object.keys(btns).forEach(function (k) {
            _btns.push({
                label: k,
                action: btns[k]
            })
        });

        return BootstrapDialog.show({
            cssClass: 'edit-row-panel',
            type: 'edit',
            title: title,
            message: text,
            buttons: _btns,
            draggable: true,
            onshow: function (dialog) {
                dialog.$modalContent.css({
                    width: "70vw"
                });
            },
            onshown: function (dialog) {
            dialog.$modalContent.position({
                of: window
            })
        }
        })
    };

   // return App.modal(text, title, btns);


})();
(function () {
    App.copyMe = function (str) {
        let tmp = document.createElement('textarea');
        tmp.value = str;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
    }

})();
(function () {
    App.dateFormats = {
        'base': 'DD.MM.YY',
        'db': 'YYYY-MM-DD',
        covert: function (date, from, to) {
            return moment(date, from).format(to);
        },
        covertToDb: function (date, from) {
            var from = from || this.base;
            return moment(date, from).format(this.db);
        },
        covertFromDb: function (date, to) {
            var to = to || this.base;
            return moment(date, this.db).format(to);
        },
        isValid: function (date, format) {
            var format = format || this.base;
            return moment(date, format).isValid();
        }
    };
    App.dateTimeFormats = {
        'base': 'DD.MM.YY HH:mm',
        'db': 'YYYY-MM-DD HH:mm',
        covert: function (date, from, to) {
            return moment(date, from).format(to);
        },
        covertToDb: function (date, from) {
            var from = from || this.base;
            return moment(date, from).format(this.db);
        },
        covertFromDb: function (date, to) {
            var to = to || this.base;
            return moment(date, this.db).format(to);
        },
        isValid: function (date, format) {
            var format = format || this.base;
            return moment(date, format).isValid();
        }
    };
})
();
Object.equals = function (first, second) {

    let cache = []; //кеш обьектов, для избежания рекурсии

    function inCache(first, second) {
        var i = cache.length;
        while (i--) if (
            (cache[i][0] === first || cache[i][0] === second) && (cache[i][1] === second || cache[i][1] === first)
        ) return true;
        return false
    }

    return function eq(f, s) {
        if (f === s) return true; //сравниваем обычным образом
        if (f instanceof Date && s instanceof Date) return +f === +s; //время
        if (typeof f !== 'object' || typeof s !== 'object' || s === null || f === null) return false; //если хотябы один из аргументов не объект (положительный случай для необъектов рассмотрен выше)
        if (inCache(f, s)) return true; //есть в кеше
        cache.push([f, s]); //кешируем

        if (Array.isArray(f) !== Array.isArray(s)) return false;
        if (Array.isArray(f)) {
            if (f.length !== s.length) return false;
            let i = f.length;
            while (i--) if (!eq(f[i], s[i])) return false; //рекурсивный вызов
        } else {
            let keys = Object.keys(f), i = keys.length; //получаем ключи
            if (Object.keys(s).length !== i) return false; //если количество ключей не совпадает
            while (i--) if (!eq(f[keys[i]], s[keys[i]])) return false; //рекурсивный вызов
        }
        return true
    }(first, second)
};


(function () {
    let Img = 'fa-cog';
    let processStack = {};
    let $image = $('#big_loading i');
    let iCog = 0;

    App.fullScreenProcesses = {
        showCog: function () {
            iCog++;
            App.fullScreenProcesses.show('fa-cog', true)
        },
        hideCog: function () {
            iCog--;
            if (iCog < 1) {
                App.fullScreenProcesses.hide();
                iCog = 0;
            }
        }
    };
    App.fullScreenProcesses.show = function (image, withRotate) {
        withRotate = withRotate || false;
        $('body').addClass('lock');

        if (withRotate && !$image.is('.fa-spin')) {
            $image.addClass('fa-spin')
        } else if (!withRotate && $image.is('.fa-spin')) {
            $image.removeClass('fa-spin')
        }

        if (Img != image) {
            $image.removeClass(Img).addClass(image);
            Img = image;
        }
        $('#big_loading').fadeIn(250);
    };
    App.fullScreenProcesses.hide = function (uin) {
        $('body').removeClass('lock');
        $('#big_loading').fadeOut(250);
    }
})();
(function(){
    App.hexToRGB = function (hex, alpha) {
                       var r = parseInt(hex.slice(1, 3), 16),
                           g = parseInt(hex.slice(3, 5), 16),
                           b = parseInt(hex.slice(5, 7), 16);

                       if (alpha) {
                           return "rgba(" + r + ", " + g + ", " + b + ", " + alpha + ")";
                       } else {
                           return "rgb(" + r + ", " + g + ", " + b + ")";
                       }
                   }
    
})();
(function(){
    $.fn.extend({
    isAttached: function(){
        return $(this).closest('html').length===1;
        }
    });
    
})();
(function () {
    App.isTopWindow = function () {
        let isFramed = false;
        try {
            isFramed = window != window.top || document != top.document || self.location != top.location;
        } catch (e) {
            isFramed = true;
        }

        return !isFramed
    }
})();
(function () {
    App.logOutput = function (log) {
        let $mes = $('<div style="overflow-x: auto">');

        let btns=[
            {
                'label': "Развернуть все",
                cssClass: 'btn-m btn-default',
                'action': function (dialog) {
                    $mes.jstree("open_all");
                }
            },
            {
                'label': "Закрыть",
                cssClass: 'btn-m btn-default',
                'action': function (dialog) {
                    dialog.close();
                }
            }];

        if (typeof log === 'string') {
            btns.splice(0, 1);
        }

        window.top.BootstrapDialog.show({
            message: $mes,
            type: BootstrapDialog.TYPE_DANGER,
            title: 'Схема расчета',
            buttons: btns,
            draggable: true,
            onshown: function (dialog) {
                dialog.$modalContent.position({
                    of: window.top
                });

                if (typeof log === 'string') {
                    $mes.html('<div style="color: white; ">'+log+'</div>');
                } else {

                    $mes.jstree({
                        "state": {"key": "leftTree"},
                        'core': {
                            'check_callback': true,
                            "open_parents": true,
                            'data': log,
                            themes: {
                                'name': 'default-dark'
                            }
                        },
                        "types": {
                            "folder": {},
                            "code": {"icon": "fa fa-cog"},
                            "cogs": {"icon": "fa fa-cogs"},
                            "error": {"icon": "fa fa-exclamation-triangle"},
                            "list": {"icon": "fa fa-code"},
                            "fixed": {"icon": "fa fa-hand-rock-o"},
                            "param": {"icon": "fa fa-hashtag"},
                            "execcode": {"icon": "fa fa-magic"},
                            "recalcs": {"icon": "fa fa-recycle"},
                            "clocks": {"icon": "fa fa-clock-o"},
                            "mbs": {"icon": "fa fa-database"},
                            "selects": {"icon": "fa fa-navicon"},
                            "!": {"icon": "fa fa-exclamation"},
                            "table_simple": App.tableTypes.simple,
                            "table_version": App.tableTypes.version,
                            "table_calcs": App.tableTypes.calcs,
                            "table_tmp": App.tableTypes.tmp,
                            "table_globcalcs": App.tableTypes.globcalcs,
                            "table_cycles": App.tableTypes.cycles,
                        },
                        "plugins": ["types", "themes"]
                    });
                }
            },
            onshow: function (dialog) {
                let width = window.top.innerWidth*0.8;

                dialog.$modalHeader.css('cursor', 'pointer');
                dialog.$modalContent.css({
                    width: width
                });
                dialog.$modalContent.find('.modal-body').css('background-color', '#333');
            }

        });
    };

})();
(function(){

    App.modal=function($text, $title, $footer){

        var div = $('<div class="modal fade" id="appNotify" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">'
            + ' <div class="modal-dialog">'
            + ' <div class="modal-content">'
            + ' <div class="modal-header">'
            + ' <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>'
            + '<h4 class="modal-title"></h4>'
            + ' </div>'
            + ' <div class="modal-body">'
            + ' </div>'
            + ' <div class="modal-footer">'
            + ' </div>'
            + ' </div>'
            + ' </div>'
            + ' </div>');


        var modal={
            body: div.find('.modal-body'),
            header: div.find('.modal-header'),
            title: div.find('.modal-title'),
            footer: div.find('.modal-footer'),
            block: div
        }

       modal.block.on('hidden.bs.modal', function(){
            $(this).remove();
        });

        if (typeof $text == 'object' && $text instanceof jQuery){
            modal.body.empty().append($text);
        }else{
            modal.body.html($text);
        }
        if ($title)  {
            modal.title.html($title);
        }
        else {
            modal.header.hide();
        }
        if ($footer)  {
            if (typeof $footer == 'object'){
                var ftr=$('<div>');
                var modal=modal;
                $.each($footer, function(btnName, func){
                    if (func == 'close'){
                        ftr.append('<button type="button" class="btn btn-default" data-dismiss="modal">'+btnName+'</button>')
                        return;
                    }
                    var btnClass='default';

                    if (typeof func == 'object'){
                        if (func.class) btnClass=func.class;
                        if (func.func) func=func.func;
                    }
                    var btn=$('<button type="button" class="btn btn-'+btnClass+'">'+btnName+'</button>');
                    ftr.append(btn);

                    if (func && typeof func =='function'){
                        btn.on('click', function(){
                            func(modal.block);
                        });
                    }
                });
                modal.footer.html(ftr.children());
            }
            else modal.footer.html($footer);
        }
        else modal.footer.hide();

        modal.block.modal('show').css('z-index', '10000');
        return modal.block;
    }
})();
(function () {
    $.expr.pseudos.multiincludes = function (obj, index, meta) {
        let $obj = $(obj).find('a');
        let haystack = ($obj.data('tokens') || $obj.text()).toString().toUpperCase().replace('ё', 'е');
        let qs = meta[3].toUpperCase().replace('ё', 'е').split(" ");
        return !qs.some(function (q) {
            return haystack.indexOf(q)===-1
        });
    };

})();
(function () {
    App.notify = function ($text, $title, $style) {

        BootstrapDialog.show({
            message: $text,
            type: BootstrapDialog.TYPE_DEFAULT,
            title: $title,
            buttons: [
                {
                    label: 'Закрыть',
                    action: function (dialogRef) {
                        dialogRef.close();
                    }

                }
            ],
            onshow: function (dialog) {
                if (!$title) {
                    dialog.$modalHeader.remove();
                }
            }
        })
        /* return App.modal($text, $title, {'Закрыть': 'close'});*/
    };
    App.topNotify = function ($text, $title, $style) {
        $style = 'success';
        $title = $title || '';
        $('#notifies').append('<div class="alert alert-' + $style + '">' +
            '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>' +
            '<strong>' + $title + '</strong>' + $text +
            '</div>');
    };

    App.popNotify = function ($text, element, timeout, container, trigger) {
        let placement = 'bottom';
        let inOptions = {};
        let class_p;
        let options = {};
        if ($text.isParams) {
            inOptions = $text;
            if ($text['element']) element = $text['element'];
            if ($text['timeout']) timeout = $text['timeout'];
            if ($text['container']) container = $text['container'];
            if ($text['trigger']) trigger = $text['trigger'];
            if ($text['placement']) placement = $text['placement'];
            if ($text['class']) class_p = $text['class'];
            $text = $text['$text'];
        }
        timeout = timeout || undefined;
        container = container || element.closest('.pcTable-scrollwrapper, .InsertPanel');
        trigger = trigger || 'manual';

        options = $.extend(options, {
            html: true,
            content: $text,
            trigger: trigger,
            container: container,
            placement: placement,
            width: "70vw",
            animation: false
        });

        if (timeout == 'default') {
            timeout = 2000;
        }

        element.on('shown.bs.popover', function () {
            let popover = element.data('bs.popover');
            let left = parseInt(popover.$tip.css('left'));
            let arrowLeft = parseInt(popover.$arrow.css('left'));
            if (left < 0) {
                popover.$tip.css('left', 0);
                popover.$arrow.css('left', (arrowLeft + left) + 'px');
            }
            if (placement === 'bottom') {
                let top = element.offset().top + element.outerHeight();
                let popovertop = popover.$tip.offset().top;
                let containerTop = container.scrollTop() - container.offset().top;
                if (popovertop - top > 10) {
                    popover.$tip.css('top', top + 2 + containerTop + (container.is('.InsertPanel') ? $('.modal-dialog').offset().top : 0));
                }
            }
        });

        element.popover(options)
        if (trigger == 'manual') {
            element.popover('show');
            if(class_p){
                $('#' + element.attr('aria-describedby')).addClass(class_p);
            }
        }

        element.on('remove destroy', function () {
            if (element && element.attr('aria-describedby'))
                element.popover('destroy');
        });
        if ($text.on) {
            $text.on('remove destroy', function () {
                if (element.length && element.attr('aria-describedby') && $('#' + element.attr('aria-describedby')).length)
                    element.popover('destroy');
                //$('#' + element.attr('aria-describedby')).remove();
            });
        }
        if (timeout) {
            setTimeout(function () {
                if (element && element.attr('aria-describedby')) {
                    element.popover('destroy');
                }
            }, timeout)
        }
        return element.attr('aria-describedby');
    }
})();
(function () {
    App.ksort = function(obj){
        var keys = Object.keys(obj).sort()
            , sortedObj = {};
        for(var i in keys) {
            sortedObj[keys[i]] = obj[keys[i]];
        }
        return sortedObj;
    };

    App.values = function (obj) {
        let list=[];
        for (let i in obj){
            list.push(obj[i])
        }
        return list;
    };
    App.keys = function (obj) {
        let list=[];
        for (let i in obj){
            list.push(i)
        }
        return list;
    };
    App.isEmpty = function(obj) {
        // null and undefined are "empty"
        if (obj == null) return true;

        // Assume if it has a length property with a non-zero value
        // that that property is correct.
        if (obj.length > 0)    return false;
        if (obj.length === 0)  return true;

        // If it isn't an object at this point
        // it is empty, but it can't be anything *but* empty
        // Is it empty?  Depends on your application.
        if (typeof obj !== "object") return true;

        // Otherwise, does it have any properties of its own?
        // Note that this doesn't handle
        // toString and valueOf enumeration bugs in IE < 9
        for (var key in obj) {
            if (hasOwnProperty.call(obj, key)) return false;
        }

        return true;
    };
    App.filter = function (obj, func) {
        let objReturn={};
        Object.keys(obj).forEach(function (key) {
            if(func(key, obj[key])){
                objReturn[key]=obj[key]
            }
        });
        return objReturn;
    }
})();
(function () {
    App.openInIframe = function (title, uri, iframeName) {
        iframeName = iframeName || 'newIframe';
        let $iframe = $('<iframe style="min-width: 500px; width: 100%; height: 70vh; border: none" name = "' + iframeName + '"></iframe>');
        BootstrapDialog.show({
            message: $iframe.attr('src', uri),
            size: BootstrapDialog.SIZE_WIDE,
            title: title,
            buttons: [
                {
                    'label': "Обновить",
                    cssClass: 'btn-m btn-default',
                    'action': function (dialog) {
                        let $iframeNew;
                        $iframeNew= $('<iframe style="min-width: 500px; width: 100%; height: 70vh; border: none" name = "' + iframeName + '"></iframe>').attr('src', uri);
                        $iframe.replaceWith($iframeNew);
                        $iframe = $iframeNew;
                    }
                },
                {
                    'label': "Открыть",
                    cssClass: 'btn-m btn-default',
                    'action': function (dialog) {
                        $('<a>').attr('href', uri).hide().appendTo('body').get(0).click();
                        dialog.close();
                    }
                },
                {
                    'label': "Открыть в новой вкладке",
                    cssClass: 'btn-m btn-default',
                    'action': function (dialog) {
                    debugger
                        let a = $('<a>').attr('href', uri).attr('target', '_blank').hide().appendTo('body')
                        a.get(0).click();
                        a.remove();
                        dialog.close();
                    }
                },
                {
                    'label': "Закрыть",
                    cssClass: 'btn-m btn-default',
                    'action': function (dialog) {
                        dialog.close();
                    }
                }
            ]
        });
    };

    App.aInIframe = function (a) {
        a = $(a);
        App.openInIframe(a.text(), a.attr('href'));
        return false;
    }

})();
(function () {

    App.reUserInterface = function (users, isNotCreatorHimSelf) {
        let UserFio =  $('#UserFio');
        UserFio.css('cursor', 'pointer');

        if (isNotCreatorHimSelf){
            App.blink(UserFio, 10, '#ffe486');
        }

        const setAuthUser=function (userId) {
            let model = App.models.table('/Main/', {}, {});

            let pcTable = $('#table').data('pctable') || {isCreatorView: true};
            model.addPcTable(pcTable);
            model.reUser(userId);
        };

        const addFioClick = function () {


            let selectDiv = $('<div class="tech-table" style="height: 220px; width: 200px;"><div class="select-btn"></div><div></div></div>');

            let select = $('<select data-size="6" class="open" title="Выберите пользователя" data-style="btn-sm btn-default" data-live-search="true" data-width="100%">');

            Object.keys(users).forEach(function (uId) {
                select.append($('<option>').text(uId).data('content', users[uId]));
            });
            let techTable = selectDiv.find('.tech-table');
            selectDiv.find('.select-btn').append(select);

            UserFio.popover({
                html: true,
                content: selectDiv,
                trigger: 'manual',
                container: 'body',
                placement: 'auto bottom',
                template: '<div class="popover" role="tooltip" style=""><div class="arrow" style="left: 50%;"></div><div class="popover-content" style=" padding: 3px 5px;"></div></div>'
            });
            select.selectpicker('render').selectpicker('toggle');
            select.data('container', techTable);
            select.on('hide.bs.select', function () {
                if (select.val()){
                    setAuthUser(select.val());
                }
                $('body').off('click.FioPopover');
                return false;
            });
            setTimeout(function () {
                select.selectpicker('render');
                UserFio.popover('show');
                let popover = $('#' + UserFio.attr('aria-describedby'));
                popover.css('top', '45px');
                select.data('selectpicker').$searchbox.focus();
                $('body').one('click.FioPopover', function (e) {
                    if (e.altKey !== undefined) {
                        UserFio.popover('destroy');
                        UserFio.one('click', addFioClick);
                    }
                });
            }, 50);
        };

        UserFio.one('click', addFioClick);


    };

})();
(function () {
    App.rgb2hex=function (rgb){
        rgb = rgb.match(/^rgba?[\s+]?\([\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?/i);
        return (rgb && rgb.length === 4) ? "#" +
            ("0" + parseInt(rgb[1],10).toString(16)).slice(-2) +
            ("0" + parseInt(rgb[2],10).toString(16)).slice(-2) +
            ("0" + parseInt(rgb[3],10).toString(16)).slice(-2) : '';
    }

})();
(function () {
    App.tableTypes={
        "simple": {"icon": "fa fa-table", 'title': 'Простая'},
        "version": {"icon": "fa fa-calendar", 'title': 'Версионная'},
        "calcs": {"icon": "fa fa-calculator", 'title': 'Расчетная в цикле'},
        "globcalcs": {"icon": "fa fa-calculator", 'title': 'Расчетная'},
        "tmp": {"icon": "fa fa-clock-o", 'title': 'Временная'},
        "cycles": {"icon": "fa fa-circle-o", 'title': 'Циклы'}
    };
})();
(function () {
    App.textWithLinks = function (text) {
        let div = $('<div>').text(text);
        let regExp = /(https?:\/\/[^\s]+)/g;

        return div.html().replace(regExp, function (url) {
            return '<a href="' + url + '" target="_blank">' + url + '</a>';
        });
    }
})();
(function () {
    var Uin = 0;
    App.getUn = function(){
        return Uin++;
    }

})();
(function () {
    $.fn.datetimepicker.defaults.icons.close = 'glyphicon glyphicon-ok';
    $.fn.datetimepicker.defaults.tooltips.close = 'Применить и закрыть';

})();
(function () {
    if (!window.Hlps)
        window.Hlps = {};
    var Hlps = window.Hlps;
    Hlps.selectpicker = {};
    Hlps.selectpicker.open = function (selectElement) {
        var btn = $(selectElement).data('selectpicker').$newElement.children()[0];
        btn = $(btn);
        if (!btn.attr('aria-expanded') || btn.attr('aria-expanded') === 'false') {
            btn.click();
        }
    }
    Hlps.selectpicker.focus = function (selectElement) {

        var focusIt=function(){
            var selectpicker = selectElement.data('this');

            if (!selectpicker || !selectElement.is('.selectpicker')){
                var i = 0;
                setTimeout(function(){

                    i++;
                    if (i<4) focusIt();
                    else return false;

                }, 1+100*i);
            }
            else {
                var btn = selectpicker.$newElement.children()[0];
                btn = $(btn);
                btn.focus();
            }
        }
        focusIt();
    }
    Hlps.selectpicker.getButton = function (selectElement) {
        var btn = $(selectElement).data('selectpicker').$newElement.children()[0];
        btn = $(btn);
       return btn;
    }

})();
(function () {
    LOGINJS = function () {

        let $fieldsDiv = $('<div><div class="form-group"><label>Email:</label><input id="elseEmail"  type="text"\n' +
            '                                                                      name="login"\n' +
            '                                                                      value=""\n' +
            '                                                                      class="form-control"\n' +
            '                /></div></div>');


        $('body').on('click', '#recover', function () {
            let buttons = [
                {
                    action: function (dialog) {
                        let email = $('#elseEmail').val().trim();
                        if (email == '') {
                            $('#elseEmail').addClass('error');
                            return;
                        }
                        let form = $('<form method="post"><input type="hidden" name="login" value=""><input type="hidden" name="recover" value="true"></form>');
                        form.find('[name="login"]').val($('#elseEmail').val());
                        form.appendTo('body');
                        form.submit();
                        dialog.close();
                    },
                    label: 'Отправить пароль на email'
                },
                {
                    action: function (dialog) {
                        dialog.close();
                    },
                    label: 'Отмена'
                }
            ];

            BootstrapDialog.show({
                message: $fieldsDiv,
                title: 'Новый пароль',
                buttons: buttons,
                draggable: true
            })
        });

        $('body').on('click', '#register', function () {
            let buttons = [
                {
                    action: function (dialog) {
                        let email = $('#elseEmail').val().trim();
                        if (email == '') {
                            $('#elseEmail').addClass('error');
                            return;
                        }
                        let form = $('<form method="post"><input type="hidden" name="login" value=""><input type="hidden" name="register" value="true"></form>');
                        form.find('[name="login"]').val($('#elseEmail').val());
                        form.appendTo('body');
                        form.submit();
                        dialog.close();
                    },
                    label: 'Зарегистрировать и отправить пароль на email'
                },
                {
                    action: function (dialog) {
                        dialog.close();
                    },
                    label: 'Отмена'
                }
            ];

            BootstrapDialog.show({
                message: $fieldsDiv,
                title: 'Регистрация',
                buttons: buttons,
                draggable: true
            })
        });

        if (!sessionStorage.getItem('browserConfirm') && (!navigator.userAgent.match(/(chrome|safari|yandex(?=\/))\/?\s*(\d+)/i) || navigator.userAgent.match(/Android|BlackBerry|iPhone|iPad|iPod|Opera Mini|IEMobile/i))) {
            let authForm = $('#auth_form');
            $('body').html('<div style="width: 600px; margin: auto; padding-top: 50px; font-size: 16px; text-align: center;" id="comeinBlock">' +
                '<img src="/imgs/start.png" alt="">' +
                '<div style="padding-bottom: 10px;">Сервис оптимизирован под десктопные броузеры Chrome, Safari, Yandex последних версий.</div>' +
                '<div><a href="#" id="comein" class="btn-default btn">Все равно хочу посмотреть</a></div></div>');
            $('#comein').on('click', function () {
                sessionStorage.setItem('browserConfirm', true);
                $('#comeinBlock').remove();
                $('body').html(authForm);
                authForm.show();
            });
        } else {
            $('#auth_form').show();
        }
    }
})();
(function () {
    restModel=function(url){

        return {
            url: url,
            create: function(){
                return $.ajax({
                    url: this.url,
                    method: 'post',
                    data: data
                })
            },
            get: function(id){
                return $.ajax({
                    url: this.url,
                    method: 'get',
                    data: {id:id}
                })
            },
            save: function(id, data){
                return $.ajax({
                    url: this.url,
                    method: 'PUT',
                    data: $.extend(data, {id:id})
                })
            }
        }
    }


})();
$(function () {

    let DocsButton= $('#docs-link');

    const addDocsClick = function () {


        let selectDiv = $('<div class="tech-table" id="DocsPopover"></div>');

        $.post('https://docs.totum.online/index.json', function (json) {
            if (json && json.length){
                json.forEach(function(row){
                    selectDiv.append('<div><i class="fa fa-external-link"></i> <a href="http://docs.totum.online'+row[1]+'" target="totum-docs">'+row[0]+'</a></div>');
                });
            }
        });

        DocsButton.popover({
            html: true,
            content: selectDiv,
            trigger: 'manual',
            container: 'body',
            placement: 'auto bottom',
            template: '<div class="popover" role="tooltip" style=""><div class="arrow" style="left: 50%;"></div><div class="popover-content" style=" padding: 3px 5px;"></div></div>'
        });

        setTimeout(function () {
            DocsButton.popover('show');
            let popover = $('#' + DocsButton.attr('aria-describedby'));
            popover.css('top', '45px');
            $('body').one('click.DocsPopover', function (e) {
                if (e.altKey !== undefined) {
                    DocsButton.popover('destroy');
                    DocsButton.one('click', addDocsClick);
                }
            });
        }, 50);
    };

    DocsButton.one('click', addDocsClick);
});
(function (window, $) {

    let BUTTONS_TIMEOUT = 2000;

    const pcTABLE_ROW_HEIGHT = 35;

    const pcTable_DATA_KEY = 'pctable';
    const pcTable_DATA_INDEX = 'pctablettemtndex';
    const pcTable_ROW_ItemId_KEY = 'pctableitemid';
    const pcTable_COLORS = {
        'blured': '#ee0c1f'
        , 'saved': '#3fbf46'
        , 'savedAndChaged': '#eeec0b'
    };

    const pcTable_TYPES = {
        'data': 'data',
        'system': 'syst',
        'global': 'glob',
        'project': 'proj'
    };

    const pcTable_PANNEL_IDS = {
        'insert': 'pcTableInsertRowPanel'
    };

    var technicalFunctions = {
        stopPropagationByKeyCode: function (event, keyCode) {
            var originalEvent = event;
            while (originalEvent) {
                if (originalEvent.keyCode == keyCode)
                    originalEvent.stopPropagation();

                if (originalEvent == event.originalEvent)
                    break;

                originalEvent = event.originalEvent;
            }
        }
    };

    var pcTableIds = 0;


(function () {



    let funcsFromTable = App.functions || [];



    let ActiveFuncNames = [];

    funcsFromTable.forEach(function (row) {

        if (!row.d) ActiveFuncNames.push(row.name);

    });



    let TOTUMjsFuncs = {};

    funcsFromTable.forEach(function (row) {

        TOTUMjsFuncs[row.name.toLowerCase()] = [row.t, 0, row.p, row.n, row.m];

    });

    let DbFieldParams = ['where', 'order', 'field', 'sfield', 'bfield', 'tfield', 'preview', 'parent', 'section', 'table', 'filter'];



    CodeMirror.defaults.scrollbarStyle = 'overlay';

    CodeMirror.defineInitHook(function (mirror) {

        try {

            if (!mirror.options.bigOneDialog) {

                let $resizer = $('<i class="fa fa-expand codemirror-expander" style="position: absolute;\n' +

                    '    right: 10px;\n' +

                    '    bottom: 10px;\n' +

                    '    z-index: 10000;\n' +

                    '    font-family: FontAwesome; cursor: pointer"></i>');



                $(mirror.display.wrapper).append($resizer);

                let newCodemirrorDiv;

                $resizer.on('click', function () {

                    newCodemirrorDiv = $('<div class="HTMLEditor" id="bigOneCodemirror" style="height: 100%;"></div>');



                    let value = mirror.getValue();

                    let editorMax;



                    window.top.BootstrapDialog.show({

                        message: newCodemirrorDiv,

                        type: null,

                        title: 'Правка текстового поля',



                        cssClass: 'fieldparams-edit-panel',

                        draggable: true,

                        onhide: function (event) {

                            mirror.setValue(editorMax.getValue());

                        },



                        onshow: function (dialog) {

                            dialog.$modalHeader.css('cursor', 'pointer');

                            dialog.$modalContent.css({

                                width: "90vw",

                                minHeight: "90vh"

                            });

                            dialog.$modalHeader.find('button.close').css('font-size', '16px').html('<i class="fa fa-compress"></i>')





                        },

                        onshown: function (dialog) {

                            editorMax = CodeMirror(newCodemirrorDiv.get(0), {

                                mode: mirror.options.mode,

                                value: value,

                                theme: 'eclipse',

                                lineNumbers: true,

                                indentWithTabs: true,

                                autoCloseTags: true,

                                bigOneDialog: dialog

                            });



                            if (mirror.table) editorMax.table = mirror.table;



                            let minheight = Math.round(dialog.$modalContent.height() - dialog.$modalHeader.outerHeight() - 40);

                            editorMax.getScrollerElement().style.minHeight = minheight + 'px';

                            newCodemirrorDiv.find('.CodeMirror').css('min-heught', minheight);

                            editorMax.focus();

                            dialog.$modalContent.position({

                                my: 'center top',

                                at: 'center top+30px',

                                of: window.top

                            });

                        }



                    });



                })

            }

        } catch (e) {

            mirror.setValue(e.message);

        }

    });





    const TotumTab = function (cm, alt, shift) {

        var cur = cm.getCursor(), token = cm.getTokenAt(cur);

        if (token.state.inFunction) {

            let start, stop;

            let line = cm.getLine(cur.line);



            if (shift) {



                start = cur.ch;

                if (line[start] === ';') start--;

                while (line[start] && [';', '('].indexOf(line[start]) === -1) {

                    start--;

                }

                stop = start;





                while (line[start] && [':', '('].indexOf(line[start]) === -1) {

                    start--;

                }

                if (line[start] === ':') {

                    start++;

                    if (line[start] === ' ') start++;

                }

                stop = cur.ch;





            } else {



                start = cur.ch;

                while (line[start] && [':', ')'].indexOf(line[start]) === -1) {

                    start++;

                }

                if (line[start] === ':') {

                    start++;

                    if (line[start] === ' ') start++;

                }





                if (line[start] === ' ') start++;

                stop = start;

                while (line[stop] && [';', ')'].indexOf(line[stop]) === -1) {

                    stop++;

                }



                if (alt) {

                    start = cur.ch;

                }

            }



            cm.setSelection({line: cur.line, ch: start}, {line: cur.line, ch: stop});

            //cm.setCursor({line: cur.line, ch: cur.ch+5});

            return true;

        }

    };



    CodeMirror.defineMode("totum", function () {

        return {

            startState: function () {

                return {inString: false, isStart: true, inFunction: false, lineName: ''};

            },

            token: function (stream, state) {





                function streamString() {

                    return stream.string.substring(stream.start, stream.pos);

                }



                function error() {

                    "use strict";

                    stream.skipToEnd();

                    return 'error';

                }



                state.lineNames = [];

                stream.lineOracle.doc.cm.getValue().split("\n").forEach(function (line) {

                    if (line.trim().length === 0 || line.indexOf('//') === 0) return '';

                    return state.lineNames.push(line.replace(/^\s*~?\s*([a-zA-Z0-9=]+)\s?:.*/, '$1'));

                });



                if (stream.pos === 0 || state.isStart === true) {

                    if (stream.string.match('^[\t\s]*\/\/')) {

                        stream.skipToEnd();

                        state.isStart = false;

                        return 'comment';

                    }

                    let classes = 'start';

                    state.isStart = true;



                    if (/[\t\n]/.test(stream.peek()) && stream.next()) {

                        while (/[\t\n]/.test(stream.peek()) && stream.next()) ;

                        return 'start-tabs';

                    }





                    if (stream.skipTo(':')) {

                        state.lineName = streamString().trim();

                        if (state.lineName.substring(0, 1) === '~') {

                            classes += " fixed";

                            state.lineName = state.lineName.substring(1);

                        }

                        if (!/^[a-z0-9_]+\s*$/i.test(state.lineName) && state.lineName !== '=' && !/^f[0-9]+\s*=\s*$/i.test(state.lineName)) {

                            return error();

                        }

                        stream.next();

                    } else return error();



                    $(stream.lineOracle.doc.cm.getWrapperElement()).find('.cm-var-not-in').each(function () {

                        let text = $(this).text();

                        text = text.replace(/\[.*/, '');

                        if (text === '$' + state.lineName || text === '#$' + state.lineName || text === '$$' + state.lineName) {

                            $(this).removeClass('cm-var-not-in')

                        }

                    });



                    let matchesCount = state.lineNames.filter(function (v) {

                        return v === state.lineName

                    }).length;

                    if (matchesCount > 1) {

                        classes += " dubbled";

                    }

                    state.isStart = false;

                    return classes;

                }

                state.isStart = false;



                switch (stream.peek()) {

                    case ' ':

                        stream.next();

                        return null;

                        break;



                    case '{':

                        stream.next();



                        if (stream.skipTo('}')) {

                            stream.next();

                        } else return error();



                        return "inVars";

                        break;



                    case '"':

                        let quote = stream.peek();

                        stream.next();



                        if (stream.skipTo(quote)) {

                            stream.next();

                        } else return error();

                        return "string";

                        break;

                    case '#':

                        stream.next();



                        while (/[a-z0-9_\[\]\$\#".]/.test(stream.peek()) && stream.next()) {

                        }

                        let classes = 'db_name';

                        let varName = stream.string.substring(stream.start + 1, stream.pos);



                        if (varName === "") return error();



                        if (varName[0] === '$') {



                            varName = varName.replace(/\[.*/g, '').slice(1);



                            if (state.lineNames.indexOf(varName) === -1) {

                                classes += " var-not-in";

                            }

                        }

                        return classes;

                        break;

                    case '@':

                        stream.next();

                        let string = stream.peek();

                        let nS;

                        while (/[a-z0-9_.\[\$\#"A-Z\]]/.test(nS = stream.peek()) && stream.next()) {

                            string += nS;

                        }

                        if (/^[a-z0-9_]{3,}\.[a-z0-9_]{2,}(\[[a-zA-Z0-9_\$\#"]+\])*$/i.test(string)) {

                            return "db_name";

                        }



                        return error();

                        break;

                    case '$':

                        stream.next();



                        if (stream.peek() === '#') {

                            stream.next();

                            while (/[a-z0-9_\[\]\$\#"]/i.test(stream.peek()) && stream.next()) {

                            }

                            return 'code-var'

                        } else {



                            while (/[a-zA-Z0-9_\[\]\$\#"]/i.test(stream.peek()) && stream.next()) {

                            }

                            let variableName = stream.string.substring(stream.start, stream.pos);

                            variableName = variableName.replace(/\[.*/g, '');

                            let classes = 'variable';

                            let varName = variableName.substring(1);

                            if (varName[0] === '$') {

                                classes += " dollar-dollar";



                                varName = varName.substring(1);

                            }



                            if (state.lineNames.indexOf(varName) === -1) {

                                classes += " var-not-in";

                            }

                            return classes;

                        }



                        break;



                    case 't':

                        if (stream.string.substring(stream.start, stream.start + 4) === 'true') {

                            stream.skipTo('e');

                            stream.next();

                            return "boolean";

                        }



                        break;



                    case 'f':

                        if (stream.string.substring(stream.start, stream.start + 5) === 'false') {

                            stream.skipTo('e');

                            stream.next();

                            return "boolean";

                        }

                        break;

                }





                state.inFuncName = false;

                if (!state.inFunction && /[a-zA-Z]/.test(stream.peek())) {



                    state.inFuncName = true;





                    if (!stream.skipTo('(')) {

                        stream.skipToEnd();

                    } else {

                        state.inFunction = true;



                    }



                    if (!/^[a-zA-Z]+[0-9]*\s*$/.test(streamString())) {

                        return error();

                    }

                    let func = TOTUMjsFuncs[streamString().trim().toLowerCase()];



                    if (!func) {

                        return error();

                    }



                    state.func = func;



                    stream.next();

                    return 'function';

                }



                if (state.inFunction) {



                    if (stream.peek() === ')') {

                        stream.next();

                        state.inFunction = false;

                        state.functionParam = '';

                        return 'function';

                    }



                    if (!state.functionParam && /[a-z_]/.test(stream.peek())) {

                        if (stream.skipTo(':')) {

                            let paramName = stream.string.substring(stream.start, stream.pos);

                            if (/^[a-z_]+\s*$/.test(paramName)) {

                                state.functionParam = paramName;

                                stream.next();

                                if (state.func[2].indexOf(paramName) === -1) return error();



                                let classes = 'functionParam';

                                if (DbFieldParams.indexOf(paramName) !== -1) {

                                    classes += ' fieldParam';

                                }

                                if (state.func[3].indexOf(paramName) !== -1) {

                                    classes += ' reqParam';

                                }

                                if (state.func[4] && state.func[4].indexOf(paramName) !== -1) {

                                    classes += ' multiParam';

                                }

                                return classes;

                            } else return error();

                        } else {

                            if (stream.skipTo(')')) {

                                return 'error fieldParam';

                            } else return error();

                        }

                    }

                    if (!state.functionParam) {

                        return error();

                    }



                    if (stream.peek() === ';') {

                        stream.next();



                        state.functionParam = '';

                        return '';

                    }



                    if (state.functionParam === 'order' && /[ad]/.test(stream.peek())) {

                        if (stream.string.substring(stream.start, stream.start + 3) === 'asc') {

                            stream.next();

                            stream.next();

                            stream.next();

                            return '';

                        } else if (stream.string.substring(stream.start, stream.start + 4) === 'desc') {

                            stream.next();

                            stream.next();

                            stream.next();

                            stream.next();

                            return '';

                        }



                    }



                    if (stream.peek() === "'") {

                        let quote = stream.peek();

                        stream.next();



                        if (stream.skipTo(quote)) {

                            stream.next();

                        } else return error();

                        return "string-name";

                    }





                }





                if (/\d|%/.test(stream.peek())) {

                    stream.next();



                    while (/[0-9.%]/.test(stream.peek()) && stream.next()) {

                    }

                    if (!/^\d+(\.\d+)?%?$/.test(stream.string.substring(stream.start, stream.pos))) {

                        return error();

                    }

                    return "number";

                }

                if (/[+-\/*!<>=]/.test(stream.peek())) {

                    stream.next();

                    return "operator";

                }





                return error();

            }

        };

    });



    function autoCloseS(cm) {

        if (cm.getOption("disableInput") || cm.getOption("mode") !== 'totum') return CodeMirror.Pass;

        var ranges = cm.listSelections(), replacements = [];

        let openHints = false;

        for (let i = 0; i < ranges.length; i++) {

            if (!ranges[i].empty()) return CodeMirror.Pass;

            var pos = ranges[i].head, tok = cm.getTokenAt(pos, true);





            if (tok.state.inFuncName || tok.type == 'function' || tok.type == 'error') {

                let func;

                let lower = tok.string.toLowerCase();

                let params = '';

                if (lower.indexOf('/') !== -1) {

                    func = lower.substring(0, lower.indexOf('/'));

                    params = lower.substring(lower.indexOf('/'));



                } else func = lower.trimRight();





                if (func = TOTUMjsFuncs[func]) {

                    let replaceText = '';

                    let newPosition = 0;

                    if (params.length) {

                        replaceText = '(';

                        let firstParamCh = 1;

                        let isFirst = true;

                        params.split('/').forEach(function (str) {

                            if (str.length === 0) ;

                            else {

                                if (!isFirst) {

                                    replaceText += '; ';

                                }

                                isFirst = false;

                                let fulled = str.indexOf(':') !== -1 && (str.slice(str.indexOf(':') + 1).trim() !== "");

                                replaceText += str + (str.indexOf(':') === -1 ? ': ' : '');



                                if (firstParamCh === 1 && !fulled) {

                                    let param = str.slice(0, str.indexOf(':'));

                                    /*if (DbFieldParams.indexOf(param)!==-1){

                                        replaceText+="''";

                                        firstParamCh = replaceText.length-1;

                                    }else{

                                        firstParamCh = replaceText.length;

                                    }*/



                                    firstParamCh = replaceText.length;

                                }

                            }

                        });



                        if (firstParamCh === 1) {

                            firstParamCh = replaceText.length;

                        } else {

                            openHints = true;

                        }



                        newPosition += firstParamCh;

                        replaceText += ')';



                    } else {

                        replaceText = func[0];

                        if (!/:/.test(replaceText)) newPosition += replaceText.length;

                        else {

                            let _firstParamCh = replaceText.indexOf(':');

                            let _firstParamStartStr = replaceText.substring(_firstParamCh);

                            let _firstParamStr = _firstParamStartStr.substring(0, _firstParamStartStr.indexOf(';') || _firstParamStartStr.indexOf(')'));



                            newPosition += _firstParamCh;



                            if (_firstParamStr.indexOf("'") !== -1) {

                                newPosition += _firstParamStr.indexOf("'") + 1;

                            } else {

                                newPosition += 2;

                            }

                            openHints = true;

                        }





                    }

                    replacements[i] = {

                        text: replaceText,

                        newPos: CodeMirror.Pos(pos.line, pos.ch - params.length + newPosition),

                        replace: CodeMirror.Pos(pos.line, pos.ch - params.length)

                    };

                } else {

                    replacements[i] = {

                        text: "()",

                        newPos: CodeMirror.Pos(pos.line, pos.ch + 1),

                        replace: CodeMirror.Pos(pos.line, pos.ch)

                    };

                }

                let info = replacements[i];

                if (info) {

                    cm.replaceRange(info.text, info.replace, ranges[i].anchor, "+insert");

                    var sel = cm.listSelections().slice(0);

                    sel[i] = {head: info.newPos, anchor: info.newPos};

                    cm.setSelections(sel);

                    if (openHints) {

                        CodeMirror.showHint(cm, CodeMirror.hint.totumVars, {});

                    }

                }

            }

        }





    }



    CodeMirror.registerHelper("hint", "totumVars", function javascriptHint(editor, options) {

        return scriptHint(editor, totumKeywords,

            function (e, cur) {

                return e.getTokenAt(cur);

            },

            options);

    });

    let AllTables = [];

    let tablesQueried = false;

    const getTables = function () {

        if (AllTables.length === 0 && !tablesQueried) {

            tablesQueried = true;

            $('#table').data('pctable').model.getAllTables().then(function (json) {

                AllTables = json.tables;

            })

        }

        return AllTables;

    };

    const renderHint = function (el, data, row) {

        $(el).append('<span class="' + row.type + '">' + (row.textVis || row.text) + (row.title === "" ? '' : ' <span class="descr">' + row.title + '</span>') + '</span>')

    };



    function scriptHint(editor, keywords, getToken, options) {

        // Find the token at the cursor

        var cur = editor.getCursor(), token = getToken(editor, cur);



        if (token.type !== 'string-name' && /\b(?:string|comment)\b/.test(token.type)) return;





        var innerMode = CodeMirror.innerMode(editor.getMode(), token.state);

        if (innerMode.mode.helperType === "json") return;

        token.state = innerMode.state;



        token.inString = token.string;



        token.state.isDb_name = false;

        token.state.showAll = true;



        options.inStart = true;



        let hintFunc = function (cm, data, completion) {

            if (completion !== undefined && completion !== null) {

                cm.replaceRange(completion.text || completion, data.from, data.to);



                if (completion.curPos) {

                    cm.setCursor({line: data.from.line, ch: completion.curPos});



                }

                if (completion.showHint) {

                    CodeMirror.showHint(cm, CodeMirror.hint.totumVars, {});

                }

            }

        };



        let match;



        keywords = keywords.slice();

        if (token.state.isStart || cur.ch === 0) {

            keywords = [];

            token.string = token.string.replace(/^[\t]+/, '');

            let tilda = '';

            if (/^~/.test(token.string)) {

                token.string.replace(/^~/, '');

                tilda = '~';

            }

            $(editor.getWrapperElement()).find('.cm-var-not-in').each(function () {

                let text = $(this).text().replace(/^(\#|\$)?\$/, '').replace(/\[.*/, '');



                keywords.push({text: tilda + text + ': ', displayText: text});

            });





        } else if (/^'.*?'?$/.test(token.string) && DbFieldParams.indexOf(token.state.functionParam) !== -1) {



            token.state.showAll = true;



            if (token.string === "''") {

                token.end = token.end;

                token.start += 1;

                token.string = '';

            } else {

                if (/'[a-z_0-9]+'/.test(token.string)) {

                    token.start += 1;

                    token.string = token.string.slice(1, cur.ch - token.start);

                } else {



                    token.string = token.string.slice(1, cur.ch - token.start);

                    token.start += 1;

                    token.end = cur.ch;



                    if (editor.getLine(cur.line)[cur.ch] === "'") {

                        token.end++;

                    }

                }





            }





            if (token.state.functionParam === 'table') {

                let tables = getTables();

                keywords = [];



                Object.keys(tables).forEach(function (name) {

                    keywords.push({

                        text: name + "'",

                        textVis: name,

                        title: tables[name].t,

                        render: renderHint,

                        type: 'item-string-name',

                        hint: hintFunc,

                        tab: true

                    })

                });



            } else {

                let line = editor.getLine(cur.line);

                let str = line.substring(0, cur.ch);

                let lastPart = line.substring(cur.ch);

                let q = lastPart.substring(0, lastPart.indexOf(')'));

                str = str.substring(str.lastIndexOf('(')) + q;

                let tblMatch;

                if (tblMatch = str.match(/table:\s*((\$#ntn)|'([a-z_0-9]*)')/)) {

                    let tableName = tblMatch[2] ? editor.table : tblMatch[3];



                    keywords = [];

                    Object.keys(AllTables[tableName].f).forEach(function (fName) {

                        keywords.push({

                            text: fName + "'",

                            textVis: fName,

                            title: AllTables[tableName].f[fName],

                            render: renderHint,

                            type: 'item-string-name', curPos: cur.ch + fName.length + 1,

                            tab: true

                        });

                    });



                    keywords.push({

                        text: 'id' + "'",

                        textVis: 'id'

                        , title: '', render: renderHint, type: 'item-string-name', curPos: cur.ch + 3,

                        tab: true

                    })

                }

            }





        } else {





            if (token.end > cur.ch) {

                token.end = cur.ch;

                token.string = token.string.slice(0, cur.ch - token.start);

            }



            /* @ */

            if (token.string.indexOf('@') === 0) {

                options.inStart = false;

                keywords = [];

                let tables = getTables();

                let matches;





                token.string = token.string.slice(1, cur.ch - token.start);

                token.start = token.start + 1;

                token.end = cur.ch;



                if (matches = token.string.match(/^([a-z_0-9]+)\./)) {

                    let tName = matches[1];



                    token.start += (tName + '.').length;

                    token.string = token.string.slice((tName + '.').length);





                    if (tables[tName] && tables[tName]["@"].length) {

                        tables[tName]["@"].forEach(function (fName) {

                            keywords.push({

                                text: fName,

                                title: tables[tName].f[fName],

                                render: renderHint,

                                type: 'item-db_name'

                            })

                        });

                    }



                } else {



                    Object.keys(tables).forEach(function (tName) {

                        if (tables[tName]["@"].length) {

                            keywords.push({

                                text: tName + '.',

                                textVis: tName,

                                title: tables[tName].t,

                                render: renderHint,

                                type: 'item-db_name',

                                showHint: true,

                                hint: hintFunc

                            })

                        }

                    });

                }



            } else if (token.string.slice(0, 2) === '$#') {



                keywords = [

                    {text: "$#lc", title: 'Пустой лист', render: renderHint, type: 'item-code-var'},

                    {text: "$#nd", title: 'дата - Y-m-d', render: renderHint, type: 'item-code-var'}, //, hint: function (cm, data, completion) {}

                    {text: "$#ndt", title: 'дата-время - Y-m-d H:i', render: renderHint, type: 'item-code-var'},

                    {

                        text: "$#ndts",

                        title: 'дата-время с секундами - Y-m-d H:i:s',

                        render: renderHint,

                        type: 'item-code-var'

                    },

                    {text: "$#nu", title: 'id пользователя', render: renderHint, type: 'item-code-var'},

                    {text: "$#nr", title: 'ids ролей пользователя', render: renderHint, type: 'item-code-var'},

                    {text: "$#nti", title: 'id таблицы', render: renderHint, type: 'item-code-var'},

                    {text: "$#ntn", title: 'NAME таблицы', render: renderHint, type: 'item-code-var'},

                    {text: "$#nth", title: 'HASH врем. таблицы', render: renderHint, type: 'item-code-var'},

                    {text: "$#nci", title: 'Cycle расчетной таблицы', render: renderHint, type: 'item-code-var'},

                    {text: "$#nf", title: 'NAME поля', render: renderHint, type: 'item-code-var'},

                    {text: "$#nl", title: 'Новая строка', render: renderHint, type: 'item-code-var'},

                    {text: "$#ids", title: 'id отмеченных галочками полей', render: renderHint, type: 'item-code-var'},

                    {

                        text: "$#nfv",

                        title: 'Значение текущего поля (для селектов/действий/форматов)',

                        render: renderHint,

                        type: 'item-code-var'

                    },

                    {

                        text: "$#onfv",

                        title: 'Прошлое значение текущего поля',

                        render: renderHint,

                        type: 'item-code-var'

                    },

                    {text: "$#nh", title: 'Текущий хост-name', render: renderHint, type: 'item-code-var'},

                ];



                const funcSort = function (firsts) {

                    let sort = keywords.slice();

                    let firstsFull = [];

                    sort = sort.filter(function (v) {

                        if (firsts.indexOf(v.text) === -1) return true;

                        else firstsFull.push(v)

                    });

                    return sort = firstsFull.concat(sort);

                };



                switch (token.state.functionParam) {

                    case 'table':

                        keywords = funcSort(['$#ntn']);

                        break;

                    case 'cycle':

                        keywords = funcSort(['$#nci']);

                        break;

                    case 'field':

                        keywords = funcSort(['$#nf', '$#nfv']);

                        break;

                }





            } else if (match = token.string.match(/^(#?\$)/)) {



                keywords = [];

                token.string = token.string.slice(match[1].length, cur.ch - token.start);

                token.start = token.start + match[1].length;

                token.end = cur.ch;



                token.state.lineNames.forEach(function (name) {

                    if (name.indexOf('=') === -1) {

                        keywords.push(name);

                    }

                });



            } else if (match = token.string.match(/^\#/)) {



                keywords = [];

                token.string = token.string.slice(1, cur.ch - token.start);

                token.start = token.start + 1;

                token.end = cur.ch;



                if(match = token.string.match(/^([a-z]{1,3}\.)/)){

                    token.string = token.string.slice(match[1].length);

                    token.start +=match[1].length;

                }



                if (editor.table && AllTables[editor.table]) {

                    Object.keys(AllTables[editor.table].f).forEach(function (fName) {

                        keywords.push({

                            text: fName,

                            title: AllTables[editor.table].f[fName],

                            render: renderHint,

                            type: 'item-db-name'

                        });

                    });

                    keywords.push({

                        text: 'id',

                        title: '',

                        render: renderHint,

                        type: 'item-db-name'

                    });

                }



            } else if (token.state.inFuncName) {

                if (!token.state.inFunction) {

                    if (match = token.string.match(/^([a-zA-Z]+)\//)) {

                        let func;

                        token.state.showAll = true;



                        if (func = TOTUMjsFuncs[match[1].toLowerCase()]) {

                            let oldStart = token.start;

                            token.start = token.start + token.string.lastIndexOf('/') + 1;

                            token.string = token.string.slice(token.string.lastIndexOf('/') + 1, cur.ch - oldStart);

                            token.end = cur.ch;

                            keywords = [];

                            func[2].forEach(function (fName) {

                                let type = '';

                                if (func[3].indexOf(fName) !== -1) type += ' item-reqParam';

                                if (func[4].indexOf(fName) !== -1) type += ' item-multiParam';

                                if (DbFieldParams.indexOf(fName) !== -1) type += ' item-fieldParam';



                                keywords.push({

                                    text: fName + ': ',

                                    textVis: fName,

                                    title: "",

                                    render: renderHint,

                                    type: type

                                });

                            })



                        }

                    } else {

                        keywords = ActiveFuncNames.slice();

                        keywords.push('true');

                        keywords.push('false')

                    }

                }

            } else if (token.state.func && (token.type === 'error fieldParam' || /(\(|;\s*)$/.test(editor.getLine(cur.line).slice(0, token.start)))) {

                keywords = [];



                token.state.func[2].forEach(function (fName) {



                    let type = '';

                    let zpt = '';



                    if (editor.getLine(cur.line).slice(cur.ch).trim() !== ')') zpt = '; ';



                    if (token.state.func[3].indexOf(fName) !== -1) type += ' item-reqParam';

                    if (token.state.func[4].indexOf(fName) !== -1) type += ' item-multiParam';

                    if (DbFieldParams.indexOf(fName) !== -1) type += ' item-fieldParam';



                    keywords.push({

                        text: fName + ': ' + zpt,

                        textVis: fName,

                        title: "",

                        render: renderHint,

                        type: type,

                        hint: hintFunc,

                        curPos: token.start + (fName + ': ' + zpt).length - zpt.length

                    });

                })

            } else {



                keywords = [

                    'true',

                    'false'



                ];

                if (token.state.functionParam === 'order') {

                    keywords = keywords.concat(['asc', 'desc'])

                }

            }



        }



        return {

            list: getCompletions(token, keywords, options),

            from: CodeMirror.Pos(cur.line, token.start),

            to: CodeMirror.Pos(cur.line, token.end)

        };

    }



    let totumKeywords = [];





    /*var fooHint = CodeMirror.hint.foo;

    CodeMirror.hint.foo = function(cm, options) {

        var result = fooHint(cm, options);

        if (result)

        return result;

    });*/





    CodeMirror.defineOption("autoCloseFunctions", false, function (cm) {

        if (cm.getOption('mode') !== 'totum') return false;



        getTables();



        var map = {name: "autoCloseFunctions"};

        map["'('"] = autoCloseS;

        cm.addKeyMap(map);



        cm.on('keydown', function (cm, event) {

            if ((event.keyCode || event.which).toString() === '9') {

                if (TotumTab(cm, event.altKey, event.shiftKey)) {



                    event.preventDefault();

                }

            }

        });



        cm.on("keyup", function (cm, event) {

            var popupKeyCodes = {

                "9": "tab",

                "13": "enter",

                "27": "escape",

                "33": "pageup",

                "34": "pagedown",

                "35": "end",

                "36": "home",

                "38": "up",

                "40": "down",

                "57": "("

            };



            //ctrl-s

            let isBigOneSave = cm.options.bigOneDialog && event.ctrlKey && (event.keyCode || event.which).toString() === '83';





            if (isBigOneSave) {

                event.stopPropagation();

                cm.options.bigOneDialog.close()



            } else if (event.ctrlKey && (event.keyCode || event.which).toString() === '191') {

                CodeMirror.commentMe(cm);

            }



            if ((event.keyCode || event.which).toString() === '27') {

                if (cm.state.completionActive) {

                    cm.state.completionActive.close();

                }

                event.stopPropagation();



            } else if (!popupKeyCodes[(event.keyCode || event.which).toString()]) {

                CodeMirror.showHint(cm, CodeMirror.hint.totumVars, {});

            }



        });

        cm.on('dblclick', function (event) {

            var cur = cm.getCursor(), token = cm.getTokenAt(cur).state;



            if (token.isStart && token.lineName !== '=') {

                let wrapper = $(cm.getWrapperElement());

                wrapper.find('.cm-variable').removeClass('light').each(function () {

                    let cmVar = $(this);

                    if (cmVar.text() === '$' + token.lineName) {

                        cmVar.addClass('light');

                    }



                })

            }



        });



    });

    /* CodeMirror.on("keyup", function (cm, event) {

         if (cm.state.completionActive && event.keyCode === 51) {

             CodeMirror.showHint(cm, CodeMirror.hint.totumVars, {});

         }

     });*/





    (function () {

        "use strict";



        CodeMirror.showHint = function (cm, getHints, options) {

            // We want a single cursor position.

            if (cm.somethingSelected()) return;

            if (getHints == null) getHints = cm.getHelper(cm.getCursor(), "hint");

            if (getHints == null) return;



            if (cm.state.completionActive) cm.state.completionActive.close();



            var completion = cm.state.completionActive = new Completion(cm, getHints, options || {});

            CodeMirror.signal(cm, "startCompletion", cm);

            if (completion.options.async)

                getHints(cm, function (hints) {

                    completion.showHints(hints);

                }, completion.options);

            else

                return completion.showHints(getHints(cm, completion.options));

        };



        CodeMirror.commentMe = function (cm) {

            var cur = cm.getCursor(), token = cm.getTokenAt(cur).state, line = cm.lineInfo(cur.line);

            let comment;

            if(comment=line.text.match(/^([\t\s]*)\/\//)){

                cm.replaceRange("", {line: cur.line , ch:comment[1].length},{line:cur.line , ch:comment[0].length});

            }else{

                comment=line.text.match(/^[\t\s]*/);

                cm.replaceRange("//", {line: cur.line , ch:comment[0].length},{line:cur.line , ch:comment[0].length});

            }



        };



        function Completion(cm, getHints, options) {

            this.cm = cm;

            this.getHints = getHints;

            this.options = options;

            this.widget = this.onClose = null;

        }



        Completion.prototype = {

            close: function () {

                if (!this.active()) return;



                if (this.widget) this.widget.close();

                if (this.onClose) this.onClose();

                this.cm.state.completionActive = null;

                CodeMirror.signal(this.cm, "endCompletion", this.cm);

            },



            active: function () {

                return this.cm.state.completionActive == this;

            },



            pick: function (data, i) {

                var completion = data.list[i];

                if (completion.hint) completion.hint(this.cm, data, completion);

                else this.cm.replaceRange(getText(completion), data.from, data.to);

                this.close();

            },



            showHints: function (data) {

                if (!data || !data.list.length || !this.active()) return this.close();

                this.showWidget(data);

            },



            showWidget: function (data) {

                this.widget = new Widget(this, data);

                CodeMirror.signal(data, "shown");



                var debounce = null, completion = this, finished;

                var closeOn = this.options.closeCharacters || /[\s()\[\]{};:>,]/;

                var startPos = this.cm.getCursor(), startLen = this.cm.getLine(startPos.line).length;



                function done() {

                    if (finished) return;

                    finished = true;

                    completion.close();

                    completion.cm.off("cursorActivity", activity);

                    CodeMirror.signal(data, "close");

                }



                function isDone() {

                    if (finished) return true;

                    if (!completion.widget) {

                        done();

                        return true;

                    }

                }



                function update() {

                    if (isDone()) return;

                    if (completion.options.async)

                        completion.getHints(completion.cm, finishUpdate, completion.options);

                    else

                        finishUpdate(completion.getHints(completion.cm, completion.options));

                }



                function finishUpdate(data) {

                    if (isDone()) return;

                    if (!data || !data.list.length) return done();

                    completion.widget.close();

                    completion.widget = new Widget(completion, data);

                }



                function activity() {

                    clearTimeout(debounce);

                    var pos = completion.cm.getCursor(), line = completion.cm.getLine(pos.line);

                    if (pos.line != startPos.line || line.length - pos.ch != startLen - startPos.ch ||

                        pos.ch < startPos.ch || completion.cm.somethingSelected() ||

                        (pos.ch && closeOn.test(line.charAt(pos.ch - 1))))

                        completion.close();

                    else

                        debounce = setTimeout(update, 170);

                }



                this.cm.on("cursorActivity", activity);

                this.onClose = done;

            }

        };



        function getText(completion) {

            if (typeof completion == "string") return completion;

            else return completion.text;

        }



        function buildKeyMap(options, handle) {

            var baseMap = {

                Up: function () {

                    handle.moveFocus(-1);

                },

                Down: function () {

                    handle.moveFocus(1);

                },

                PageUp: function () {

                    handle.moveFocus(-handle.menuSize());

                },

                PageDown: function () {

                    handle.moveFocus(handle.menuSize());

                },

                Home: function () {

                    handle.setFocus(0);

                },

                End: function () {

                    handle.setFocus(handle.length);

                },

                Enter: handle.pick,

                Tab: handle.pick,

                Esc: handle.close

            };

            var ourMap = options.customKeys ? {} : baseMap;



            function addBinding(key, val) {

                var bound;

                if (typeof val != "string")

                    bound = function (cm) {

                        return val(cm, handle);

                    };

                // This mechanism is deprecated

                else if (baseMap.hasOwnProperty(val))

                    bound = baseMap[val];

                else

                    bound = val;

                ourMap[key] = bound;

            }



            if (options.customKeys)

                for (var key in options.customKeys) if (options.customKeys.hasOwnProperty(key))

                    addBinding(key, options.customKeys[key]);

            if (options.extraKeys)

                for (var key in options.extraKeys) if (options.extraKeys.hasOwnProperty(key))

                    addBinding(key, options.extraKeys[key]);

            return ourMap;

        }



        function Widget(completion, data) {

            this.completion = completion;

            this.data = data;

            var widget = this, cm = completion.cm, options = completion.options;



            var hints = this.hints = window.top.document.createElement("ul");

            hints.className = "CodeMirror-hints";

            this.selectedHint = 0;



            var completions = data.list;

            for (var i = 0; i < completions.length; ++i) {

                var elt = hints.appendChild(window.top.document.createElement("li")), cur = completions[i];

                var className = "CodeMirror-hint" + (i ? "" : " CodeMirror-hint-active");

                if (cur.className != null) className = cur.className + " " + className;

                elt.className = className;

                if (cur.render) cur.render(elt, data, cur);

                else elt.appendChild(window.top.document.createTextNode(cur.displayText || getText(cur)));

                elt.hintId = i;

            }



            var pos = cm.cursorCoords(options.alignWithWord !== false ? data.from : null);

            var left = pos.left, top = pos.bottom + 3, below = true;

            hints.style.left = left + "px";

            hints.style.top = top + "px";

            // If we're at the edge of the screen, then we want the menu to appear on the left of the cursor.

            var winW = window.innerWidth || Math.max(window.top.document.body.offsetWidth, window.top.document.documentElement.offsetWidth);

            var winH = window.innerHeight || Math.max(window.top.document.body.offsetHeight, window.top.document.documentElement.offsetHeight);

            var box = hints.getBoundingClientRect();

            var overlapX = box.right - winW, overlapY = box.bottom - winH;

            if (overlapX > 0) {

                if (box.right - box.left > winW) {

                    hints.style.width = (winW - 5) + "px";

                    overlapX -= (box.right - box.left) - winW;

                }

                hints.style.left = (left = pos.left - overlapX) + "px";

            }

            if (overlapY > 0) {

                var height = box.bottom - box.top;

                if (box.top - (pos.bottom - pos.top) - height > 0) {

                    overlapY = height + (pos.bottom - pos.top);

                    below = false;

                } else if (height > winH) {

                    hints.style.height = (winH - 5) + "px";

                    overlapY -= height - winH;

                }

                hints.style.top = (top = pos.bottom - overlapY) + "px";

            }

            (options.container || window.top.document.body).appendChild(hints);



            cm.addKeyMap(this.keyMap = buildKeyMap(options, {

                moveFocus: function (n) {

                    widget.changeActive(widget.selectedHint + n);

                },

                setFocus: function (n) {

                    widget.changeActive(n);

                },

                menuSize: function () {

                    return widget.screenAmount();

                },

                length: completions.length,

                close: function () {

                    completion.close();

                },

                pick: function () {

                    widget.pick();

                }

            }));



            if (options.closeOnUnfocus !== false) {

                var closingOnBlur;

                cm.on("blur", this.onBlur = function () {

                    closingOnBlur = setTimeout(function () {

                        completion.close();

                    }, 100);

                });

                cm.on("focus", this.onFocus = function () {

                    clearTimeout(closingOnBlur);

                });

            }



            var startScroll = cm.getScrollInfo();

            cm.on("scroll", this.onScroll = function () {

                var curScroll = cm.getScrollInfo(), editor = cm.getWrapperElement().getBoundingClientRect();

                var newTop = top + startScroll.top - curScroll.top;

                var point = newTop - (window.pageYOffset || (window.top.document.documentElement || window.top.document.body).scrollTop);

                if (!below) point += hints.offsetHeight;

                if (point <= editor.top || point >= editor.bottom) return completion.close();

                hints.style.top = newTop + "px";

                hints.style.left = (left + startScroll.left - curScroll.left) + "px";

            });



            CodeMirror.on(hints, "click", function (e) {

                var t = e.target || e.srcElement;

                while (t.nodeName === 'SPAN') t = t.parentNode;

                if (t.hintId != null) {

                    widget.changeActive(t.hintId);

                    widget.pick();

                }

            });

            /*CodeMirror.on(hints, "click", function (e) {

                var t = e.target || e.srcElement;

                while (t.nodeName ==='SPAN') t = t.parentNode;



                if (t.hintId != null) widget.changeActive(t.hintId);

            });*/

            CodeMirror.on(hints, "mousedown", function () {

                setTimeout(function () {

                    cm.focus();

                }, 20);

            });



            CodeMirror.signal(data, "select", completions[0], hints.firstChild);

            return true;

        }



        Widget.prototype = {

            close: function () {

                if (this.completion.widget != this) return;

                this.completion.widget = null;

                this.hints.parentNode.removeChild(this.hints);

                this.completion.cm.removeKeyMap(this.keyMap);



                var cm = this.completion.cm;

                if (this.completion.options.closeOnUnfocus !== false) {

                    cm.off("blur", this.onBlur);

                    cm.off("focus", this.onFocus);

                }

                cm.off("scroll", this.onScroll);

            },



            pick: function () {

                this.completion.pick(this.data, this.selectedHint);

            },



            changeActive: function (i) {

                i = Math.max(0, Math.min(i, this.data.list.length - 1));

                if (this.selectedHint == i) return;

                var node = this.hints.childNodes[this.selectedHint];

                node.className = node.className.replace(" CodeMirror-hint-active", "");

                node = this.hints.childNodes[this.selectedHint = i];

                node.className += " CodeMirror-hint-active";

                if (node.offsetTop < this.hints.scrollTop)

                    this.hints.scrollTop = node.offsetTop - 3;

                else if (node.offsetTop + node.offsetHeight > this.hints.scrollTop + this.hints.clientHeight)

                    this.hints.scrollTop = node.offsetTop + node.offsetHeight - this.hints.clientHeight + 3;

                CodeMirror.signal(this.data, "select", this.data.list[this.selectedHint], node);

            },



            screenAmount: function () {

                return Math.floor(this.hints.clientHeight / this.hints.firstChild.offsetHeight) || 1;

            }

        };

    })();



    function getCompletions(token, keywords, options) {

        var foundStart = [], foundOther = [], start = token.string.toLowerCase(),

            global = options && options.globalScope || window;



        if (!token.state.showAll && start === "") return found;



        function maybeAdd(str) {

            let testStr, testStrRus = "";

            if (typeof str === "string") {

                testStr = str.toLowerCase();

            } else {

                testStr = str.text.toLowerCase();

                if (str.title) {

                    testStrRus = str.title.toLowerCase();

                }

            }



            if (!arrayContains(foundStart, testStr) && !arrayContains(foundOther, testStr)) {

                let tLeft;



                if (testStr.lastIndexOf(start, 0) === 0) foundStart.push(str);

                else if (testStrRus.lastIndexOf(start, 0) === 0) foundStart.push(str);

                else if (testStrRus.indexOf(start, 0) !== -1) foundOther.push(str);

                else if (!options.inStart && testStr.indexOf(start) !== -1) foundOther.push(str);

            }

        }



        keywords.forEach(maybeAdd);



        if (foundStart.length === 1 && foundStart[0] === start) return [];

        if (foundStart.length === 1 && foundStart[0].text === start) return [];

        if (foundOther.length === 1 && foundOther[0] === start) return [];

        if (foundOther.length === 1 && foundOther[0].text === start) return [];



        return foundStart.concat(foundOther);

    }



    function arrayContains(arr, item) {

        return arr.indexOf(item) !== -1;

    }



})();
(function() {

    CodeMirror.extendMode("css", {
        commentStart: "/*",
        commentEnd: "*/",
        newlineAfterToken: function(type, content) {
            return /^[;{}]$/.test(content);
        }
    });

    CodeMirror.extendMode("javascript", {
        commentStart: "/*",
        commentEnd: "*/",
        // FIXME semicolons inside of for
        newlineAfterToken: function(type, content, textAfter, state) {
            if (this.jsonMode) {
                return /^[\[,{]$/.test(content) || /^}/.test(textAfter);
            } else {
                if (content == ";" && state.lexical && state.lexical.type == ")") return false;
                return /^[;{}]$/.test(content) && !/^;/.test(textAfter);
            }
        }
    });

    CodeMirror.extendMode("xml", {
        commentStart: "<!--",
        commentEnd: "-->",
        newlineAfterToken: function(type, content, textAfter) {
            return type == "tag" && />$/.test(content) || /^</.test(textAfter);
        }
    });

    // Comment/uncomment the specified range
    CodeMirror.defineExtension("commentRange", function (isComment, from, to) {
        var cm = this, curMode = CodeMirror.innerMode(cm.getMode(), cm.getTokenAt(from).state).mode;
        cm.operation(function() {
            if (isComment) { // Comment range
                cm.replaceRange(curMode.commentEnd, to);
                cm.replaceRange(curMode.commentStart, from);
                if (from.line == to.line && from.ch == to.ch) // An empty comment inserted - put cursor inside
                    cm.setCursor(from.line, from.ch + curMode.commentStart.length);
            } else { // Uncomment range
                var selText = cm.getRange(from, to);
                var startIndex = selText.indexOf(curMode.commentStart);
                var endIndex = selText.lastIndexOf(curMode.commentEnd);
                if (startIndex > -1 && endIndex > -1 && endIndex > startIndex) {
                    // Take string till comment start
                    selText = selText.substr(0, startIndex)
                        // From comment start till comment end
                        + selText.substring(startIndex + curMode.commentStart.length, endIndex)
                        // From comment end till string end
                        + selText.substr(endIndex + curMode.commentEnd.length);
                }
                cm.replaceRange(selText, from, to);
            }
        });
    });

    // Applies automatic mode-aware indentation to the specified range
    CodeMirror.defineExtension("autoIndentRange", function (from, to) {
        var cmInstance = this;
        this.operation(function () {
            for (var i = from.line; i <= to.line; i++) {
                cmInstance.indentLine(i, "smart");
            }
        });
    });

    // Applies automatic formatting to the specified range
    CodeMirror.defineExtension("autoFormatRange", function (from, to) {
        var cm = this;
        var outer = cm.getMode(), text = cm.getRange(from, to).split("\n");
        var state = CodeMirror.copyState(outer, cm.getTokenAt(from).state);
        var tabSize = cm.getOption("tabSize");

        var out = "", lines = 0, atSol = from.ch == 0;
        function newline() {
            out += "\n";
            atSol = true;
            ++lines;
        }

        for (var i = 0; i < text.length; ++i) {
            var stream = new CodeMirror.StringStream(text[i], tabSize);
            while (!stream.eol()) {
                var inner = CodeMirror.innerMode(outer, state);
                var style = outer.token(stream, state), cur = stream.current();
                stream.start = stream.pos;
                if (!atSol || /\S/.test(cur)) {
                    out += cur;
                    atSol = false;
                }
                if (!atSol && inner.mode.newlineAfterToken &&
                    inner.mode.newlineAfterToken(style, cur, stream.string.slice(stream.pos) || text[i+1] || "", inner.state))
                    newline();
            }
            if (!stream.pos && outer.blankLine) outer.blankLine(state);
            if (!atSol) newline();
        }

        cm.operation(function () {
            cm.replaceRange(out, from, to);
            for (var cur = from.line + 1, end = from.line + lines; cur <= end; ++cur)
                cm.indentLine(cur, "smart");
            cm.setSelection(from, cm.getCursor(false));
        });
    });

    $(function () {

    });
})();


    let fieldTypes = {};

var defaultField = {
    sortable: false, //'acs', 'desc'
    width: 50,
    icon: 'fa-font',
    editable: false,
    required: true,
    insertable: false,
    type: 'string',
    getPanelVal(val) {
        return val;
    },
    getEditVal: function (input) {
        var val = input.val().trim();
        var error = false,
            notify;
        if (this.required && (val === undefined || val === '' || val === null)) {
            notify = 'Поле ' + this.title + ' должно быть заполнено';
            error = true;
        }

        if (this.regexp && val !== '') {
            var r = new RegExp(this.regexp);
            if (!r.test(val)) {
                notify = this.regexpErrorText || 'regexp не проходит - "' + this.regexp + '"';
                notify = 'Ошибка заполнения поля "' + this.title + '": ' + notify;
                error = true;
            }
        }
        if (error) throw notify;
        return val;
    },
    getEditElement: function ($oldInput, oldValue, item, enterClbk, escClbk, blurClbk, tabindex) {
        var $input = $('<input type="text" class="form-control" name="cell_edit" autocomplete="off" autocorrect="off" />');
        if (typeof tabindex !== 'undefined') $input.attr('tabindex', tabindex);

        var field = this;
        oldValue = oldValue.v;
        $input.val(oldValue).on('keyup', function (event) {
            switch (event.keyCode) {
                case 13:
                    try {
                        $input.data('enterClicked', true);
                        enterClbk($(this), event);
                    } catch (err) {
                        $input.data('enterClicked', false);
                        App.popNotify(err, $input, 'default');
                        field.focusElement($input);
                    }
                    break;
                case 27:
                    escClbk($(this), event);
                    break;
            }
        });


        $input.on('blur', function (event) {
            blurClbk($input, event);
        });
        return $input.select();
    },
    checkEditRegExp: function (val) {
        if (!this.warningEditRegExp) return true;
        try {
            let matches = this.warningEditRegExp.match(/^\/(.*?)\/([a-z]*)$/);
            let regExp = new RegExp(matches[1], matches[2]);

            return regExp.test(val);
        } catch (e) {
            return true;
        }
    },
    getCellText: function (fieldValue) {
        if (this.url === true && fieldValue) {
            let target = this.openIn || 'window';
            switch (target) {
                case 'window':
                    target = '_self';
                    break;
                case 'newWindow':
                    target = '_blank';
                    break;
            }
            let a = $('<a class="uri" target="' + target + '">').attr('href', fieldValue).text(fieldValue);
            if (target === 'iframe') {
                a.attr('onclick', 'return App.aInIframe(this);');
            }
            return a;
        }
        return fieldValue;
    },
    getPanelText: function (fieldValue, td, item) {
        return this.getCellText(fieldValue, td, item);
    },
    getCopyText: function (fieldValue, item) {
        let res = this.getPanelText(fieldValue.v, null, item);

        if (typeof res === 'string') return res;

        const checkDiv=function(res){
            if(res && res.each && {}.toString.call(res.each) === '[object Function]'){
                let result = '';
                res.each(function () {
                    if (result !== "") result += "\n";
                    result += $(this).text();
                });
                return result;
            }
            return res;
        };

        if (res===null) return  "";

        if (typeof res ==='object' && !(res instanceof jQuery)){
            let text;
            let def = $.Deferred();
            res.done(function (data) {
                def.resolve(checkDiv(data))
            }).fail(function(){
                def.resolve('Не удалось загрузить данные')
            });

            return def;
        }



        return checkDiv(res);
    },
    focusElement: function (input) {
        input.focus();
    },
    isDataModified: function (editVal, itemVal) {

        editVal = editVal + '';
        itemVal = itemVal + '';

        editVal === 'null' ? editVal = '' : false;
        itemVal === 'null' ? itemVal = '' : false;
        itemVal === (this.errorText || 'ОШБК!') ? itemVal = '' : false;
        itemVal === 'undefined' ? itemVal = '' : false;

        return editVal !== itemVal;
    },
    getFilterDataByValue: function(valObj){
        let filterVals=[];
        this.addDataToFilter(filterVals, valObj);
        return Object.keys(filterVals)[0];
    },
    addDataToFilter: function (filterVals, valObj) {
        let hash;
        if (valObj.v === null) {
            hash = 'null'.hashCode();
        } else {
            hash = valObj.v.toString().hashCode();
        }
        filterVals[hash] = typeof valObj.v === "string" ? valObj.v.replace(/"/g, "&quot;") : valObj.v;
    },
    checkIsFiltered: function (fieldVal, filters) {
        let val = fieldVal.v;
        var contains = false;
        filters.forEach(function (v) {
            var valstr = val == null ? 'null' : val.toString();

            if (v === valstr.hashCode().toString()) {
                contains = true;
                return false;
            }
        });
        return contains;
    },
    getCellTextInPanel: function (fieldValue, td, item) {
        return this.getCellText(fieldValue, td, item);
    }
};
fieldTypes.text = {
    width: 50,
    icon: 'fa-align-left',
    type: 'Text',
    isPanelField: true,
    getEditVal: function (div) {
        var val = div.data('val').trim();
        return val;
    },
    getCellText: function (fieldValue) {
        if (typeof fieldValue !== 'string') return '';
        let length = fieldValue.length;
        return $('<div>').text(fieldValue.substring(0, this.viewTextMaxLength) + (length > this.viewTextMaxLength ? '...' : '')).text();
    },
    getValue: function (value, item, isModulPanel) {
        "use strict";

        if (isModulPanel || (typeof value === 'string' && value.length < this.viewTextMaxLength)) {
            let def = $.Deferred();
            setTimeout(function () {

                if (!value) value = '';

                def.resolve({'value': value})
            }, 20);
            return def;
        }

        let data = {'fieldName': this.name};
        if (item.id) {
            data['rowId'] = item.id;
        }
        return this.pcTable.model.getValue(data, this.table_id);
    },
    getPanelTextWithLinks: function (text) {
        let field = this;
        if (field.textType === 'text') {
            let div = $('<div>');
            div.html(App.textWithLinks(text));
            text = div;
        } else {
            text = $('<div>').text(text);
        }
        return text;
    },
    getPanelText: function (fieldValue, td, item) {
        if (typeof fieldValue !== 'string') return '';

        let field = this;

        if (fieldValue.length <= this.viewTextMaxLength) return field.getPanelTextWithLinks.call(field, fieldValue);

        let def = $.Deferred();

        this.getValue(fieldValue, item, false).then(function (json) {
            def.resolve($('<div>').append(field.getPanelTextWithLinks.call(field, json.value)));

        }).fail(function () {
            def.reject();
        });

        return def.promise();
    },
    getEditElement: function ($oldInput, oldValueParam, item, enterClbk, escClbk, blurClbk, tabindex, editNow) {

        let field = this;
        let div = $('<div>');
        let dialog = $('<div>').css('min-height', 200);
        let buttons;
        let element = $('<div class="HTMLEditor">');

        oldValueParam = oldValueParam.v || '';

        let formFill = function () {

            field.getValue(oldValueParam, item, !editNow).then(function (json) {
                let editor;
                div.append(element);
                element.empty().appendTo(dialog);

                if (field.textType === 'json') {
                    editor = new JSONEditor(element.get(0), {});
                    try {
                        if (json.value !== '') {
                            editor.setText(json.value);
                        }
                    } catch (e) {
                        App.modal('Ошибка формата JSON ')
                    }
                    element.css('min-height', 200);

                    let btn = $('<a href="#" style="padding-top: 5px; display: inline-block; padding-left: 20px;">Вручную</a>').on('click', function () {
                        let div = $('<div>');
                        let textarea = $('<textarea class="form-control" style="height: 350px;">').val(JSON.stringify(editor.get(), null, 2)).appendTo(div);

                        BootstrapDialog.show({
                            message: div,
                            type: null,
                            title: 'Ручное изменение json-поля',
                            buttons: [
                                {
                                    'label': "Сохранить",
                                    cssClass: 'btn-m btn-warning',
                                    action: function (dialog) {
                                        try {
                                            editor.setText(textarea.val());
                                            dialog.close();
                                        } catch (e) {
                                            App.modal('Ошибка формата JSON')
                                        }
                                    }
                                }, {
                                    'label': "Закрыть",
                                    cssClass: 'btn-m btn-default',
                                    'action': function (dialog) {
                                        dialog.close();
                                    }
                                }
                            ],
                            cssClass: 'fieldparams-edit-panel',
                            draggable: true,
                            onhide: function (event) {
                                // escClbk(div, event);
                            },
                            onshown: function (dialog) {
                                dialog.$modalContent.position({
                                    of: window
                                });
                            },
                            onshow: function (dialog) {
                                dialog.$modalHeader.css('cursor', 'pointer')
                                dialog.$modalContent.css({
                                    width: 500
                                });
                            }

                        });
                        return false;
                    });
                    element.find('.jsoneditor-menu').append(btn);
                } else {


                    let mode = 'text';
                    switch (field.textType) {
                        case 'html':
                            mode = 'text/html';
                            break;
                        case 'totum':
                            mode = 'totum';
                            break;
                        case 'markdown':
                            mode = 'text/x-markdown';
                            break;
                        case 'xml':
                            mode = 'application/xml';
                            break;
                        case 'css':
                            mode = 'text/css';
                            break;
                        case 'javascript':
                            mode = 'text/javascript';
                            break;
                    }


                    let el = $('<div>').appendTo(element);
                    let options = {
                        value: json.value,
                        mode: mode,
                        minHeight: '150px',
                        readOnly: false,
                        theme: 'eclipse',
                        lineNumbers: true,
                        indentWithTabs: true,
                        autoCloseTags: true
                    };

                    if (mode === 'text') {
                        options.lineNumbers = false;
                        options.lineWrapping = true;
                    }

                    editor = CodeMirror(el.get(0), options);
                    editor.on('paste', function (cm, event) {
                        setTimeout(function () {
                            editor.refresh();
                        }, 1);
                    });
                    if (field.pcTable && field.pcTable.tableRow.name === 'tables') {
                        editor.table = item.name.v || item.name;
                    }
                    editor.getScrollerElement().style.minHeight = '350px';

                }

                element.data('editor', editor);
            });

        };

        const save = function (dialog, event, notEnter) {
            if (field.textType === 'json') {
                div.data('val', JSON.stringify(element.data('editor').get()));
            } else {
                div.data('val', element.data('editor').getValue());
            }
            if (!notEnter) {
                enterClbk(div, {});
                dialog.close();
            }
        };


        buttons = [];

        let btnsSave = {
            'label': "Сохранить",
            cssClass: 'btn-m btn-warning',
            action: save
        }, btnsClose = {
            'label': "Закрыть",
            cssClass: 'btn-m btn-default',
            'action': function (dialog) {
                escClbk(div, {});
                dialog.close();
            }
        };

        if (['xml', 'html'].indexOf(field.textType) !== -1) {
            buttons.unshift({
                label: 'Форматировать',
                action: function () {
                    let editor = element.data('editor');
                    let totalLines = editor.lineCount();
                    let totalChars = editor.getValue().length;
                    editor.autoFormatRange({line: 0, ch: 0}, {line: totalLines, ch: totalChars});
                }
            });
        }

        let title = 'Текст поля <b>' + (this.title) + ', ' + field.textType + '</b>';
        let eventName = 'ctrlS.textedit';

        if (editNow) {
            let btnClicked = false;
            setTimeout(function () {
                let cdiv = div.closest('td').find('.cdiv');
                if (cdiv.length > 0) {
                    cdiv.data('bs.popover').options.content.find('.btn').each(function () {
                        let btn = $(this);
                        let buttn = {};
                        buttn.label = btn.data('name');
                        buttn.cssClass = btn.attr('class').replace('btn-sm', 'btn-m');
                        buttn.icon = btn.find('i').attr('class');
                        buttn.save = btn.data('save');
                        buttn.click = btn.data('click');
                        buttn.action = function (dialog) {
                            if (buttn.save) {
                                save(dialog, {}, true);
                            }
                            buttn.click({});
                            btnClicked = true;
                            dialog.close();
                        };

                        buttons.push(buttn)
                    });
                    cdiv.popover('destroy');
                } else {
                    buttons.push(btnsSave);
                    buttons.push(btnsClose)
                }

                BootstrapDialog.show({
                    message: dialog,
                    type: null,
                    title: title,
                    buttons: buttons,
                    cssClass: 'fieldparams-edit-panel',
                    draggable: true,
                    onhide: function (dialog) {
                        $('body').off(eventName);
                        if (!btnClicked) {
                            blurClbk(div, {});
                        }
                    },
                    onshown: function (dialog) {
                        dialog.$modalContent.position({
                            of: $('body'),
                            my: 'top+50px',
                            at: 'top'
                        });
                        formFill();
                    },
                    onshow: function (dialog) {
                        dialog.$modalHeader.css('cursor', 'pointer')
                        dialog.$modalContent.css({
                            width: 900
                        });

                        $('body').on(eventName, function (event) {
                            save(dialog, event);
                        });
                    }

                });


            }, 1);


            div.text('Редактирование в форме').addClass('edit-in-form');
        } else {
            div.on('focus click', 'button', function () {
                let _buttons = buttons.splice();
                _buttons.push(btnsSave);
                _buttons.push(btnsClose);

                var div = $(this).closest('div');
                BootstrapDialog.show({
                    message: dialog,
                    type: null,
                    cssClass: 'fieldparams-edit-panel',
                    title: title,
                    buttons: _buttons,
                    draggable: true,
                    onhide: function (event) {
                        $('body').off(eventName);
                        escClbk(div, event);
                    },
                    onshown: function (dialog) {
                        dialog.$modalHeader.css('cursor', 'pointer');
                        formFill();

                        dialog.$modalContent.css({
                            width: 900
                        });
                        $('body').on(eventName, function (event) {
                            save(dialog, event);
                        });
                    }
                })
            });

            let btn = $('<button class="btn btn-default btn-sm text-edit-button">').text('Редактировать текст');
            if (tabindex) btn.attr('tabindex', tabindex);

            div.append(btn);
            setTimeout(function () {
                if (div.closest('.InsertPanel').length !== 0 && oldValueParam != "") {
                    let textDiv = field.getCellTextInPanel(oldValueParam, div, item);
                    textDiv.css('padding-bottom', "5px");
                    div.prepend(textDiv)
                }
            }, 10)
        }
        return div.data('val', oldValueParam);//.attr('data-category', category).attr('data-category', category);

    }, getCellTextInPanel: function (fieldValue, td, item) {


        return $("<div>").append(this.getCellText(fieldValue, td, item)).css('white-space', 'pre-wrap');
    }
};
fieldTypes.checkbox = {
    icon: 'fa-check-square',
    getEditVal: function (input) {
        return input.is(':checked')?true:false;
    },
    getCellText: function (fieldValue) {
        if (fieldValue === true) return '✓';
        if (fieldValue === false) return '-';
        return '';
    },
    getEditElement: function ($oldInput, oldValue, item, enterClbk, escClbk, blurClbk, tabindex) {
        var $input = $('<input type="checkbox" name="cell_edit"/>');

        if (tabindex) $input.attr('tabindex', tabindex);

        $input.on('keyup', function (event) {
            if (event.keyCode == 13) {
                setTimeout(function () {
                    enterClbk($input, event);
                }, 20);

            }
        })
        var field = this;

        if (oldValue.v===true){
            $input.prop('checked', true);
        }

        $input.on('click', function (event) {
            enterClbk($input, event);
        });
        return $input;
    }

};
fieldTypes.string = {

};
fieldTypes.number = {
    icon: 'fa-hashtag',
    getEditVal: function (input) {

        let val = input.val().trim();

        if (this.required && (val === undefined || val === '' || val === null)) {
            throw 'Поле ' + this.title + ' должно быть заполнено';
        }


        if (this.regexp) {
            var r = new RegExp(this.regexp);
            if (!r.test(val)) {
                throw 'Ошибка заполнения поля "' + this.title + '"';
            }
        }

        if (val === '') return '';

        let valNew = val.replace(/[^\-()\d/*+.,%:\/]/g, '');
        if (!/^(\+|\*|\%|\/|\:)?(\-?[\d]+((\.|\,)[\d]+)?)%?$/.test(valNew)) {
            throw 'Здесь должно быть число';
        }
        val = val.replace(/,/, '.');
        return val;

    },
    getCopyText: function (val, td, item) {
        if (val === null || val === undefined || val === '' || val.v === null) return '';

        return (val.v).toString().replace(/\./g, ',');
    },
    getCellText: function (val, td, item) {
        if (val === null || val === undefined || val === '') return '';

        if (this.currency) {
            let options = {};
            if (this.dectimalPlaces) {
                options.minimumFractionDigits = this.dectimalPlaces;
            }
            return parseFloat(val).toLocaleString('ru-RU', options);
        }
        return val;
    }
};
fieldTypes.date = {
    icon: 'fa-calendar-o',
    getEditVal: function (input) {
        if (this.required && input.val().trim() == '') throw 'Поле должно быть заполнено';
        if (!input.val().trim()) return '';
        let date = input.data('calendar').data('DateTimePicker').date();

        return this.getDbString(date);
    },
    getEditElement: function ($oldInput, oldValue, item, enterClbk, escClbk, blurClbk, tabindex) {
        var $input = $('<input type="text" name="cell_edit" class="form-control" autocomplete="off" autocorrect="off" />');

        if (tabindex) $input.attr('tabindex', tabindex);

        var field = this;
        let format = this.getFormat();

        $input.data('AppUin', App.getUn());

        oldValue = oldValue.v;


        $input.val(this.getViewString(oldValue));


        let cParent = $('<div>');
        let popoverClass;
        if (this.dateTime){
            popoverClass="date-popover"
        }
        var calendar = $('<div></div>').appendTo(cParent);
        calendar.on('dp.change', function (event) {
            if (event.oldDate === null && field.dateTime && (!$input.val() || $input.val() === '')) {
                let date = event.date;
                let now = moment();
                if (date.format('HH:mm') === now.format('HH:mm')) {
                    date = date.hours(0).minutes(0);
                }
                $input.val(date.format(format));
                setDateTimePickerDate();
            } else {
                $input.val(event.date.format(format));
            }
        });
        let timeoutObject;

        $input.on('keyup', function (event) {
            if (timeoutObject) clearTimeout(timeoutObject);
            if (event.keyCode === 13) {
                setDateTimePickerDate();
                enterClbk($(this), event);
            } else if (event.keyCode === 27) {
                escClbk($(this), event);
            } else if (event.keyCode >= 48) {
                timeoutObject = setTimeout(function () {
                    setDateTimePickerDate();
                }, 2000);
            }
        });
        let popoverId, popover;

        const setDateTimePickerDate = function () {
            "use strict";
            let val = $input.val();

            if (val) {
                val = moment(val, format);

            } else {
                val = "";
            }
            try {
                calendar.data("DateTimePicker").date(val);
            } catch (e) {

            }
        };

        setTimeout(function () {
            let cdiv = $input.closest('td').find('.cdiv');
            if (cdiv.length > 0) {
                cParent.append(cdiv.data('bs.popover').options.content);
                cdiv.popover('destroy');
                popover = $('#' + App.popNotify({
                    $text: cParent,
                    element: cdiv,
                    container: field.pcTable._container,
                    isParams: true,
                    placement: 'bottom',
                    class: popoverClass,
                }));
                $input.on('focus click', function () {
                    popover.show();
                });

            } else {
                $input.on('focus click', function () {
                    if (!popover) {
                        popoverId = App.popNotify(cParent, $input);
                        popover = $('#' + popoverId);
                    }
                    calendar.data("DateTimePicker").show();
                    popover.show();
                    setDateTimePickerDate();
                });
            }

        }, 20);
        $input.on('blur', function (event) {
            setTimeout(function () {
                if (popover && popover.is(':visible')) {
                    popover.hide();
                    setDateTimePickerDate();
                    blurClbk($input, event);
                }
            }, 200);
        });


        calendar.datetimepicker({
            inline: true,
            format: format,
            useCurrent: false,
            showClose: false,
            locale: 'ru',
            sideBySide: true,
            collapse: false
            // defaultDate: moment().format("YYYY-MM-DD 00:00")
        });

        if (oldValue) {
            try {
                calendar.data("DateTimePicker").date(field.getMoment(oldValue));
            } catch (e) {

            }
        } else {
            $input.val("");
        }

        $input.data('calendar', calendar);
        return $input;
    },

    getCellText: function (fieldValue) {
        if (!fieldValue || fieldValue === null) return '';
        return this.getViewString(fieldValue);
    },
    getViewString: function (val) {
        if (!val) return '';
        if (this.dateTime) {
            return App.dateTimeFormats.covertFromDb(val, this.getFormat());
        } else {
            return App.dateFormats.covertFromDb(val, this.getFormat());
        }
    },
    getDbString: function (val) {
        if (!val) return '';

        if (this.dateTime) {
            return App.dateTimeFormats.covertToDb(val, this.getFormat());
        } else {
            return App.dateFormats.covertToDb(val, this.getFormat());
        }
    },
    getMoment: function (val) {

        if (this.dateTime) {
            return moment(val, App.dateTimeFormats.db)
        } else {
            return moment(val, App.dateFormats.db)
        }
    },
    addDataToFilter: function (filterVals, valObj) {

        let hash;
        if (valObj.v === null) {
            hash = 'null'.hashCode();
        } else {
            hash = valObj.v.toString().hashCode();
        }
        let format = this.dateTime ? App.dateTimeFormats : App.dateFormats;

        filterVals[hash] = typeof valObj.v === "string" ? format.covertFromDb(valObj.v) : valObj.v;

    },
    getFormat: function () {
        let format = this.dateFormat;
        if (!format) {
            if (this.dateTime) {
                format = 'd.m.y H:i';
            } else {
                format = 'd.m.y';
            }
        }
        let replaces = {
            'd': 'DD',
            'D': 'ddd',
            'j': 'M',
            'z': 'DDD',
            'W': 'W',
            'm': 'MM',
            'M': 'MMM',
            'n': 'M',
            'y': 'YY',
            'Y': 'YYYY',
            'H': 'HH',
            'i': 'mm',
            's': 'ss',
        };
        let formatNew = '';
        for (let i = 0; i < format.length; i++) {
            let letter = format[i];
            formatNew += replaces[letter] || letter;
        }
        return formatNew;
    }

};




fieldTypes.unic = {
    icon: 'fa-fire',
};
fieldTypes.file = {
    icon: 'fa-file-image-o',
    getSize: function (size) {
        if (size > 100 * 1024) {
            return ' ' + (Math.round(size / (1024 * 1024) * 10) / 10).toLocaleString() + 'Mb'
        } else {
            return ' ' + Math.round(size / 1024).toLocaleString() + 'Kb'
        }
    },
    getCellText: function (fieldValue) {
        if (!fieldValue || fieldValue === null || fieldValue.length == 0) return '';
        if (this.multiple && fieldValue.length > 1) {
            return fieldValue.length.toString() + ' файлов';
        }
        return $('<a href="/fls/' + fieldValue[0].file + '" download="' + $('<div>').text(fieldValue[0].name).html() + '">').text(fieldValue[0].name);
    },
    getCopyText: function (fieldValue, item) {
        fieldValue = fieldValue.v;
        if (!fieldValue || fieldValue === null || fieldValue.length == 0) return '';
        let field = this;
        let toCopy = '';
        fieldValue.forEach(function (file) {
            if (toCopy !== '') toCopy += "\n";
            toCopy += file.name+' '+window.location.protocol + '//' + window.location.host + '/fls/' + file.file+' '+field.getSize(file.size);
        });
        return toCopy;
    },
    getPanelText: function (fieldValue) {
        if (!fieldValue || fieldValue === null || fieldValue.length == 0) return '';
        let div = $('<div>');
        let field = this;
        let toCopy = '';
        let imgRand = Math.random();
        fieldValue.forEach(function (file) {
            let img = '';
            let _class = '';
            if (['jpg', 'png'].indexOf(file.ext) !== -1) {
                img = '<img src="/fls/' + file.file + '_thumb.jpg?rand=' + imgRand + '"/>';
                _class = 'with-img';
            }
            $('<div>').addClass(_class).appendTo(div).append($(img + '<br/><a href="/fls/' + file.file + '" download="' + $('<div>').text(file.name).html() + '">').text(file.name)).append(field.getSize(file.size));

            if (toCopy !== '') toCopy += "\n";
            toCopy += window.location.protocol + '//' + window.location.host + '/fls/' + file.file;
        });
        return div.data('text', toCopy);
    },
    getEditVal: function (div) {
        if (this.required && div.data('val') == '') throw 'Поле должно быть заполнено';
        return div.data('val');
    },
    getEditElement: function ($oldInput, oldValue, item, enterClbk, escClbk, blurClbk, tabindex, editNow) {
        let field = this;
        let div = $('<div>');
        let dialogBody = $('<div>').css('min-height', 200);
        let buttons, dialog;
        let Files = oldValue.v || [];
        let isEntered = false;

        const printFile = function (file) {
            let addDiv = $('<div class="filePart"><div><span class="name"></span><span class="size"></span><button class="btn btn-danger btn-xs remove"><i class="fa fa-remove"></i></button></div></div>');

            let fl = {
                name: file.name,
                type: file.type,
                tmpfile: file.tmpfile,
                size: file.size,
                file: file.file,
                ext: file.ext
            };
            let regExpName = new RegExp('^' + field.pcTable.tableRow.id + '_' + (item.id ? item.id : ''));
            if (file.file && !regExpName.test(file.file)) {
                addDiv.find('.remove').remove();
            }

            addDiv.data('file', fl);
            addDiv.find('.name').text(file.name);
            addDiv.find('.size').text(field.getSize(file.size));
            if (!file.file) {
                addDiv.append('<div class="progressbar">&nbsp;</div>');
            } else {
                let a = $('<a>').attr('href', '/fls/' + file.file).attr('download', file.name);
                addDiv.find('.name').wrap(a);
                if (['jpg', 'png'].indexOf(file.ext) !== -1) {
                    $('<img>').attr('src', '/fls/' + file.file + '_thumb.jpg?rand=' + Math.random()).insertBefore(addDiv.find('.name'));
                    addDiv.addClass('with-img')
                }

            }
            if (file.tmpfile) {
                addDiv.addClass('addFile');
                let process = addDiv.find('.progressbar');
                process.text('Требуется сохранение элемента для привязки файла');
            }
            return addDiv;
        };

        const saveDisable = function (disable) {
            dialog.$modalFooter.find('button:first').prop('disabled', disable);
        };

        const formFill = function () {
            dialogBody.empty();
            let addForm = $('<div>').appendTo(dialogBody);
            let btn = $('<button class="btn btn-default btn-sm">Добавить файл' + (field.multiple ? 'ы' : '') + '</button>');
            addForm.append(btn);
            btn.wrap('<div class="addFilesButton">');

            const checkBtnDisable = function () {
                if (!field.multiple) {
                    if (dialogBody.find('.filePart').length > 0) {
                        btn.prop('disabled', true);
                    }
                    else {
                        btn.prop('disabled', false);
                    }
                }
            };


            //Вывести файлы
            Files.forEach(function (fl) {
                let part = printFile(fl).appendTo(dialogBody);
                part.on('click', '.remove', function () {
                    part.remove();
                    checkBtnDisable();
                });
            });

            checkBtnDisable();

            btn.on('click', function () {
                let fileAdd = $('<input type="file" name = "file" ' + (field.multiple ? 'multiple' : '') + ' accept="' + field.accept + '" style="display: block; position: absolute; top: -3000px"/>');
                $('body').append(fileAdd);
                fileAdd.click();
                fileAdd.on('change', function () {
                        if (this.files) {
                            let deffs = [];
                            saveDisable(true);

                            for (let i = 0, numFiles = this.files.length; i < numFiles; i++) {
                                let file = this.files[i];
                                let addDiv = printFile(file).addClass('addFile').appendTo(dialogBody);
                                checkBtnDisable();


                                let process = addDiv.find('.progressbar');

                                if (file.size > 10 * 1024 * 1024) {
                                    process.text('Ошибка - файл больше 10 Mb').css({
                                        'box-shadow': 'none',
                                        'background-color': '#ffe486'
                                    });
                                    addDiv.on('click', '.remove', function () {
                                        addDiv.remove();
                                        checkBtnDisable();
                                    });
                                    continue;
                                }


                                let xhr = new XMLHttpRequest();
                                let deff = $.Deferred();

                                addDiv.on('click', '.remove', function () {
                                    addDiv.remove();
                                    xhr.abort();
                                    deff.resolve();
                                    checkBtnDisable();
                                });

                                xhr.upload.onprogress = function (event) {
                                    process.css('box-shadow', 'inset ' + Math.round(parseInt(process.width()) * event.loaded / event.total).toString() + 'px 0px 0 0 #85FF82');
                                    if (event.loaded === event.total) {
                                        process.text('Проверка файла сервером');
                                    }
                                };


                                xhr.onload = xhr.onerror = function (mess) {
                                    deff.resolve();

                                    if (this.status === 200) {
                                        try {
                                            let ans = JSON.parse(this.responseText);
                                            if (ans.fname) {
                                                process.text('Готово');
                                                addDiv.data('file').tmpfile = ans.fname;
                                                return;
                                            }
                                        } catch (e) {

                                        }
                                    }
                                    addDiv.data('file', null);
                                    process.text('Ошибка').css({'box-shadow': 'none', 'background-color': '#ffe486'})

                                };


                                xhr.open("POST", "/Table/", true);

                                let formData = new FormData();
                                formData.append("file", file);
                                formData.append("method", 'tmpFileUpload');
                                xhr.send(formData);
                                deffs.push(deff.promise());
                            }
                            $.when(...deffs).then(function () {
                                saveDisable(false)
                            })
                        }
                    }
                );
            });

        };

        const save = function (dialog) {
            let files = [];
            dialog.$modalContent.find('.filePart').each(function () {
                let fileDiv = $(this), file = fileDiv.data('file');
                if (file) {
                    files.push(file)
                }
            });
            div.data('val', files);
            Files = files;
            isEntered = true;
            enterClbk(div, {});
            dialog.close();
        };


        buttons = [
            {
                'label': "Сохранить",
                cssClass: 'btn-m btn-warning',
                action: save
            }, {
                'label': "Закрыть",
                cssClass: 'btn-m btn-default',
                'action': function (dialog) {
                    dialog.close();
                }
            }
        ];

        let title = 'Форма файлов <b>' + (this.title) + '</b>';
        let eventName = 'ctrlS.textdialog';

        let showDialog = function (div) {

            dialog = BootstrapDialog.show({
                message: dialogBody,
                type: null,
                cssClass: 'fieldparams-edit-panel',
                title: title,
                buttons: buttons,
                draggable: true,
                onhide: function (event) {
                    $('body').off(eventName);
                    if (!isEntered) {
                        escClbk(div, event);
                    }
                },
                onshown: function (dialog) {
                    dialog.$modalContent.position({
                        of: $('body'),
                        my: 'top+50px',
                        at: 'top'
                    });

                },
                onshown: function (dialog) {
                    dialog.$modalHeader.css('cursor', 'pointer');
                    dialog.$modalContent.css({
                        width: 600
                    });
                    formFill();

                    $('body').on(eventName, function (event) {
                        save(dialog);
                    });

                }
            })
        };

        if (editNow) {

            showDialog(div);
            div.text('Редактирование в форме').addClass('edit-in-form');
        }
        else {
            div.on('focus click', 'button', function () {
                showDialog($(this).closest('div'))
            });

            let btn = $('<button class="btn btn-default btn-sm text-edit-button">').text('Редактировать поле');
            if (tabindex) btn.attr('tabindex', tabindex);

            div.append(btn);

        }
        return div.data('val', Files);
    },
    isDataModified: function (edited, fromItem) {

        if ([null, ''].indexOf(edited) !== -1 && [null, ''].indexOf(fromItem) !== -1) return false;
        if ([null, ''].indexOf(edited) !== -1 || [null, ''].indexOf(fromItem) !== -1) return true;

        return !Object.equals(fromItem, edited);
    }
};
fieldTypes.n = $.extend({}, fieldTypes.default, {
    name: 'n',
    width: 52,
    title: 'Порядок',
    category: 'column',
    hidden: true,
    showInWeb: true,
    getCellText: function (val, td, item) {
        let format = item.f || {};
        td.addClass('n');

        if (!item.id || format.block || format.blockOrder || item.__inserted){
            return '';
        }



        return $('<span class="btns"><button class="btn btn-xxs btn-default"><i class="fa fa-angle-up"></i></button> <button class="btn btn-xxs btn-default"><i class="fa fa-angle-down"></i></button></span>');
    }

});

fieldTypes.listRow = $.extend({}, fieldTypes.default, {
    icon: 'fa-code',
    isPanelField: true,
    getPanelTextAsTable: function (fieldValue) {
        if (typeof fieldValue === 'object' && fieldValue !== null && fieldValue.settings && fieldValue.data && fieldValue.settings.columns && fieldValue.settings.columns.length) {
            const table = $('<table class="json-table">');
            let field = this;
            let settings = fieldValue.settings;
            let columns = settings.columns;
            let headRow = false;
            try {
                if (settings.headRow) {
                    const tr = $('<tr>').appendTo(table);
                    if (typeof settings.headRow === 'boolean') {
                        columns.forEach(function (column) {
                            $('<td>').text(column).appendTo(tr).addClass('head');
                        })
                    } else {
                        columns.forEach(function (column) {
                            $('<td>').text(settings.headRow[column]).appendTo(tr).addClass('head');
                        })
                    }
                }

                fieldValue.data.forEach(function (listItem) {
                    const tr = $('<tr>').appendTo(table);
                    columns.forEach(function (column) {
                        let val = listItem[column];
                        if (typeof val != "string" || val === "") {
                            val = JSON.stringify(val)
                        }
                        const td = $('<td>').text(val).appendTo(tr);
                    });
                    if (settings.headColumn) {
                        tr.find('td:first').addClass('head');
                    }
                });
                return table;

            } catch (e) {
                console.log(e);

            }
        }
    },
    getPanelText: function (fieldValue, td, item) {
        let def = $.Deferred();
        let field = this;

        const panelHtmlResult = function (val) {
            let table = field.getPanelTextAsTable.call(field, val);
            if (table) {
                table.copyText = JSON.stringify(val)
                def.resolve(table);
                return;
            }
            def.resolve($('<div>').text(JSON.stringify(val, null, 2)));
        };


        if (typeof fieldValue !== 'string') {
            panelHtmlResult(fieldValue);
        } else {
            this.getValue(fieldValue, item, false).then(function (json) {
                panelHtmlResult(json.value)

            }).fail(function () {
                def.reject();
            });
        }

        return def.promise();
    },
    getValue: function (value, item, isModulPanel) {
        "use strict";
        let def = $.Deferred();

        if (isModulPanel || this.category === "filter" || typeof value === "object" || !value) {
            def.resolve({value: value});
            return def;
        } else {
            let data = {'fieldName': this.name};
            if (item.id) {
                data['rowId'] = item.id;
            }
            this.pcTable.model.getValue(data, this.table_id).then(function (json) {
                def.resolve(json);
            })
        }


        return def;
    },
    getEditElement: function ($oldInput, oldValueParam, item, enterClbk, escClbk, blurClbk, tabindex, editNow) {

        let field = this;
        let div = $('<div>');
        let dialog = $('<div>').css('min-height', 200);
        let buttons;
        let element = $('<div class="HTMLEditor">');

        oldValueParam = oldValueParam.v || '';

        let formFill = function () {

            field.getValue(oldValueParam, item, !editNow).then(function (json) {
                let editor;
                div.append(element);
                element.empty().appendTo(dialog);

                editor = new JSONEditor(element.get(0), {});
                try {
                    if (json.value !== '') {
                        editor.setText(JSON.stringify(json.value));
                    }
                } catch (e) {
                    App.modal('Ошибка формата JSON ')
                }
                element.css('min-height', 200);

                let btn = $('<a href="#" style="padding-top: 5px; display: inline-block; padding-left: 20px;">Вручную</a>').on('click', function () {
                    let div = $('<div>');
                    let textarea = $('<textarea class="form-control" style="height: 350px;">').val(JSON.stringify(editor.get(), null, 2)).appendTo(div);

                    BootstrapDialog.show({
                        message: div,
                        type: null,
                        title: 'Ручное изменение json-поля',
                        buttons: [
                            {
                                'label': "Сохранить",
                                cssClass: 'btn-m btn-warning',
                                action: function (dialog) {
                                    try {
                                        editor.setText(textarea.val());
                                        dialog.close();
                                    } catch (e) {
                                        App.modal('Ошибка формата JSON')
                                    }
                                }
                            }, {
                                'label': "Закрыть",
                                cssClass: 'btn-m btn-default',
                                'action': function (dialog) {
                                    dialog.close();
                                }
                            }
                        ],
                        cssClass: 'fieldparams-edit-panel',
                        draggable: true,
                        onhide: function (event) {
                            // escClbk(div, event);
                        },
                        onshown: function (dialog) {
                            dialog.$modalContent.position({
                                of: window
                            });
                        },
                        onshow: function (dialog) {
                            dialog.$modalHeader.css('cursor', 'pointer')
                            dialog.$modalContent.css({
                                width: 500
                            });
                        }

                    });
                    return false;
                });
                element.find('.jsoneditor-menu').append(btn);
                element.data('editor', editor);
            });

        };

        const save = function (dialog, event, notEnter) {
            div.data('val', element.data('editor').get());
            if (!notEnter) {
                enterClbk(div, {});
                dialog.close();
            }
        };


        buttons = [];

        let btnsSave = {
            'label': "Сохранить",
            cssClass: 'btn-m btn-warning',
            action: save
        }, btnsClose = {
            'label': "Закрыть",
            cssClass: 'btn-m btn-default',
            'action': function (dialog) {
                escClbk(div, {});
                dialog.close();
            }
        };

        let title = 'Текст поля <b>' + (this.title) + '</b>';
        let eventName = 'ctrlS.textedit';

        if (editNow) {
            let btnClicked = false;
            setTimeout(function () {
                let cdiv = div.closest('td').find('.cdiv');
                if (cdiv.length > 0) {
                    cdiv.data('bs.popover').options.content.find('.btn').each(function () {
                        let btn = $(this);
                        let buttn = {};
                        buttn.label = btn.data('name');
                        buttn.cssClass = btn.attr('class').replace('btn-sm', 'btn-m');
                        buttn.icon = btn.find('i').attr('class');
                        buttn.save = btn.data('save');
                        buttn.click = btn.data('click');
                        buttn.action = function (dialog) {
                            if (buttn.save) {
                                save(dialog, {}, true);
                            }
                            buttn.click({});
                            btnClicked = true;
                            dialog.close();
                        };

                        buttons.push(buttn)
                    });
                    cdiv.popover('destroy');
                } else {
                    buttons.push(btnsSave);
                    buttons.push(btnsClose)
                }

                BootstrapDialog.show({
                    message: dialog,
                    type: null,
                    title: title,
                    buttons: buttons,
                    cssClass: 'fieldparams-edit-panel',
                    draggable: true,
                    onhide: function (dialog) {
                        $('body').off(eventName);
                        if (!btnClicked) {
                            blurClbk(div, {});
                        }
                    },
                    onshown: function (dialog) {
                        dialog.$modalContent.position({
                            of: $('body'),
                            my: 'top+50px',
                            at: 'top'
                        });
                        formFill();
                    },
                    onshow: function (dialog) {
                        dialog.$modalHeader.css('cursor', 'pointer')
                        dialog.$modalContent.css({
                            width: 900
                        });

                        $('body').on(eventName, function (event) {
                            save(dialog, event);
                        });
                    }

                });


            }, 1);


            div.text('Редактирование в форме').addClass('edit-in-form');
        } else {
            div.on('focus click', 'button', function () {
                let _buttons = buttons.splice();
                _buttons.push(btnsSave);
                _buttons.push(btnsClose);

                var div = $(this).closest('div');
                BootstrapDialog.show({
                    message: dialog,
                    type: null,
                    cssClass: 'fieldparams-edit-panel',
                    title: title,
                    buttons: _buttons,
                    draggable: true,
                    onhide: function (event) {
                        $('body').off(eventName);
                        escClbk(div, event);
                    },
                    onshown: function (dialog) {
                        dialog.$modalHeader.css('cursor', 'pointer');
                        formFill();

                        dialog.$modalContent.css({
                            width: 900
                        });
                        $('body').on(eventName, function (event) {
                            save(dialog, event);
                        });
                    }
                })
            });

            let btn = $('<button class="btn btn-default btn-sm text-edit-button">').text('Редактировать список/json');
            if (tabindex) btn.attr('tabindex', tabindex);

            div.append(btn);

        }
        return div.data('val', oldValueParam);//.attr('data-category', category).attr('data-category', category);

    },
    isDataModified: function (editVal, itemVal) {
        if (editVal === "") editVal = null;
        if (itemVal === "") itemVal = null;

        if (itemVal === editVal) return false;

        if (Object.equals(editVal, itemVal)) return false;

        return true;


    },
    getEditVal: function (div) {
        return div.data('val');
    },
    getCellText: function (fieldValue) {
        if (typeof fieldValue != 'string') {
            return JSON.stringify(fieldValue)
        }
        return fieldValue;
    },
});


fieldTypes.password = {
    icon: 'fa-lock',
    getEditVal: function(input){
        var val=input.val().trim();
        var error = false;
        if (val === undefined || val === '' || val === null) {
            notify = 'Поле ' + this.title + ' должно быть заполнено';
            error = true;
        }
        if (error) throw notify;

        return val;
    },
    getCellText: function (val) {
        return '**PASSWORD**';
    },
    getEditElement: function ($oldInput, oldValue, item, enterClbk, escClbk, blurClbk, tabindex) {
        var $input = $('<input type="password" name="cell_edit" class="form-control" autocomplete="off" autocorrect="off" placeholder="'+(oldValue?'Поменять пароль':'Новый пароль')+'"/>');
        $input.on('save-me', function (event) {
            enterClbk($(this), event);
        });


        if (tabindex) $input.attr('tabindex', tabindex);

        var field = this;
        oldValue=oldValue.v;
        $input.on('keyup', function (event) {
            switch (event.keyCode) {
                case 13:
                    try{
                        $input.data('enterClicked', true);
                        enterClbk($(this), event);
                    }
                    catch (err){
                        $input.data('enterClicked', false);
                        App.popNotify(err, $input, 'default');
                        field.focusElement($input);
                    }
                    break;
                case 27:
                    escClbk($(this), event);
                    break;
            }
        })

        var blur = function(event) {
            blurClbk($input, event);
            return;
        }
        $input.one('blur', function (event) {
            setTimeout(function(){blur(event)}, 50);
        });
        return $input.select();
    },
};
fieldTypes.select = {
    icon: 'fa-th-list',
    getEditVal: function (div) {
        if (div.data('input')) {
            var val = div.data('input').selectpicker('val');
            if (val === null) {
                if (this.multiple) val = [];
            }
            return val;
        }
    },
    loadPreviewPanel(panel, fieldName, item, val) {
        let $def = $.Deferred();
        panel.html('<div class="center"><i class="fa fa-spinner fa-spin"></i></div>');
        this.pcTable.model.loadPreviewHtml(fieldName, item, val).then(function (json) {
            if (panel) {

                let html = $('<div>');
                json.previews.forEach(
                    function (preview) {
                        let $_html = $('<div class="preview">');

                        switch (preview[2]) {
                            case 'file':
                                window.imgRand = window.imgRand || Math.random();

                                if (Array.isArray(preview[1])) {
                                    preview[1].forEach(function (file) {
                                        if (['jpg', 'png'].indexOf(file.ext) !== -1) {
                                            $_html.append($('<a href="/fls/' + file.file + '" target="_blank">').html('<img src="/fls/' + file.file + '_thumb.jpg?rand=' + window.imgRand + '"/><br/>'));
                                        }
                                        $_html.append($('<a href="/fls/' + file.file + '" target="_blank">').text(file.name + ' ' + Math.round(file.size / 1024).toLocaleString('ru-RU') + ' Kb'));
                                    });
                                }
                                break;
                            case 'html':
                                $_html.text(preview[0]);
                                break;
                            case 'text':
                                $_html.text(App.textWithLinks(preview[0]));
                                break;
                            case 'currency':
                            case 'number':
                                if (preview[2] === 'currency') {
                                    try {
                                        $_html.text(parseFloat(preview[1]).toLocaleString('ru-RU'));
                                    } catch (e) {
                                        $_html.text(preview[1]);
                                    }
                                } else {
                                    $_html.text(preview[1]);
                                }

                                if (preview[3]['unitType']) {
                                    $_html.append(' ' + preview[3]['unitType']);
                                }

                                break;
                            case 'url':
                                $_html = $('<div>').append($('<a target="_blank">').text(preview[1]).attr('href', preview[1]));
                                break;
                            default:
                                $_html = $('<div>').text(preview[1]);
                        }

                        html.append($('<div class="title">').text(preview[0]));
                        html.append($_html);
                    }
                );
                panel.empty().append(html);
            }
            $def.resolve()
        }).fail(function () {
            $def.reject();
        });
        return $def;
    },
    previewPanel: function (span, li) {

        let panel = $('<div id="selectPanel" class="text preview" style="white-space: pre-wrap; height: 200px;">');
        let field = this;

        let item = {};
        if (this.category === 'column') {
            if (span.data('id')) {
                item = this.pcTable._getItemById(span.data('id'));
            } else {
                item = this.pcTable._insertItem;
            }
        } else {
            item = this.pcTable.data_params;
        }

        li.popover({
            html: true,
            content: panel,
            trigger: 'manual',
            container: 'body',
            placement: 'auto right',
            animation: false
        }).popover('show');
        let popover = $('#' + li.attr('aria-describedby')).css('z-index', 10000);

        const destroyPopover = function(){
            if (li.attr('aria-describedby')) {
                if (popover.length) {
                    li.off('.preview');
                    popover.off('.preview');
                    popover.remove();
                }
            }
        };

        li.on('mouseout.preview', function () {
            setTimeout(function () {
                if (popover && !popover.is(':hover')) destroyPopover();
            }, 300);
        });
        popover.on('mouseout.preview', function () {
            if (!popover.is(':hover') && li && !li.is(':hover')) destroyPopover();
        });


        li.one('remove destroy', function () {
            if (li.attr('aria-describedby')) {
                li.popover('destroy');
            }
        });



        field.loadPreviewPanel(panel, span.data('field'), item, span.data('val')).then(function () {
            const _t = function(){
                if (li && !li.height()) destroyPopover();
                else setTimeout(_t, 500);
            };
            _t();
        });

    },
    getEditElement: function ($oldParent, oldValue, item, enterClbk, escClbk, blurClbk, tabindex) {
        "use strict";

        if (!oldValue) oldValue = {};

        let field = this,
            input,
            divParent,
            LISTs,
            val = oldValue.v || null;

        if (field.multiple && typeof val === 'string') {
            try{
                val = JSON.parse(val);
            }catch (e) {
                val = [];
            }
        }

        if ($oldParent && $oldParent.data('input')) {
            divParent = $oldParent;
            input = divParent.data('input');
            input.data('is-rendered', true);

            LISTs = input.data('LISTs');

        } else {
            const getTitle = function () {

                let title = '-----';
                if (field.category === 'filter') {
                    title = 'Пустое';
                    if(field.selectFilterWithEmptyText){
                        title=field.selectFilterWithEmptyText
                    }
                }
                else if (field.withEmptyVal && field.withEmptyVal.trim() !== '') title = field.withEmptyVal;

                return title;
            };

            input = $('<select class="form-control" ' + (this.multiple == true ? 'multiple ' : '') + ' data-size="auto" style="display: none;" name="cell_insert" data-style="btn-sm btn-default" data-width="css-width" data-live-search="true" data-title="' + getTitle() + '">').width(this.width);
            divParent = input.wrap('<div>').parent().append('<div class="text-center mark-loading"></div>');
            if (tabindex) input.attr('tabindex', tabindex);
            input.data('AppUin', App.getUn());
            divParent.data('input', input);

            LISTs = {
                isListForLoad: true,
                innerList: [],
                innerIndexed: [],
                isSliced: true,
                isPreview: false
            };
            if (field.list) {
                LISTs = field.list;
            }
            input.data('LISTs', LISTs);
        }


        let GetLoadListDeffered = function (q) {
            let def = $.Deferred();
            let itemTmp = {};
            Object.keys(item).forEach(function (k) {
                //Фильтруем jquery-объекты из item
                if (!/^\$/.test(k)) {
                    if (k === 'id') {
                        itemTmp[k] = item[k];
                    } else {
                        if (item[k] !== null && typeof item[k] === 'object' && Object.keys(item[k]).indexOf('v') !== -1) {
                            itemTmp[k] = item[k]['v'];
                        } else {
                            itemTmp[k] = item[k];
                        }
                    }
                }
            });
            if (divParent.isAttached()) {
                divParent.append('<i class="fa fa-cog fa-spin fa-3x loading" style="position: absolute; z-index: 1' +
                    '    right: 1px;' +
                    '    top: 1px;' +
                    '    font-size: 8px;"/>');
            }
            field.pcTable.model.getEditSelect(itemTmp, field.name, q, null).then(function (json) {
                divParent.find('.loading').remove();

                LISTs.innerList = json.list ? json.list : [];
                LISTs.innerIndexed = json.indexed ? json.indexed : {};
                LISTs.isSliced = json.sliced;
                LISTs.isPreview = json.previewdata;

                if (!field.codeSelectIndividual && (q === null || q === '' || q === undefined) && !LISTs.isSliced) {
                    field.list = LISTs;
                    LISTs.isListForLoad = false;
                }


                def.resolve()
            }, function () {
                def.reject();
            });
            return def.promise();
        };


        setTimeout(function () {
            if (divParent.length && divParent.isAttached() && divParent.find('.mark-loading').length) {
                divParent.find('.mark-loading').html('<i class="fa fa-spinner"/>');
            }
        }, 200);


        const addValues = function (val, q) {
            "use strict";
            let optgroups = {'Выбранное': $('<optgroup label="Выбранное">'), '': $('<optgroup label="">')};
            let checked = optgroups['Выбранное'];
            const createOption = function (val, text, deleted, subtext) {
                subtext = subtext ? $('<small class="text-muted">').text(subtext) : '';

                let option = $('<option>').text(val);
                let content = $('<div>').text((text === null || text === '' ? '[' + val + ']' : text));
                if (subtext) {
                    content.append(subtext);
                }
                content = content.html();

                if (deleted) {
                    option.attr('data-content', '<span class="text" style="text-decoration: line-through">' + content + '</span>');
                } else {
                    let $span = $('<span class="text" >' + content + '</span>');
                    if (LISTs.isPreview) {
                        $span.addClass('select-with-preview');
                        $span.attr('data-id', item.id);
                        $span.attr('data-field', field.name);
                        $span.attr('data-val', val);
                    }
                    option.data('content', $span.get(0).outerHTML);
                }
                return option;
            };

            let isLikedFunc = function () {
                    return true;
                };
            if (q && q !== '') {
                let qs=q.toLowerCase().replace('ё', 'е').split(" ");
                isLikedFunc = function (v) {
                    let text=v!==null?v.toString().toLowerCase().replace('ё', 'е'):"";
                    return !qs.some(function (q) {
                        return text.indexOf(q)===-1
                    })
                }
            }

            let vals = {};
            let checkedVal;

            if (val || field.category === 'filter') {
                const addCheckedOpts = function (key) {

                    if(key===null) key="";

                    let v = LISTs.innerIndexed[key], opt;
                    if (!v) {
                        opt = createOption(key, key, true, null);
                    } else {
                        opt = createOption(key, v[0], false, v[1]);
                    }

                    checked.append(opt);
                    vals[key] = 1;
                    if (v) {
                        if (!isLikedFunc(v[0])) opt.addClass('hidden');
                        return true;
                    } else return false;
                };

                if (field.multiple) {

                    if (Array.isArray(val)) {
                        val.forEach(addCheckedOpts);
                        checkedVal = Object.keys(vals);
                    } else {
                        if (val !== undefined) {
                            addCheckedOpts(val);
                            checkedVal = [val];
                        }
                    }
                } else {
                    addCheckedOpts(val);
                    checkedVal = val;
                }

            }


            if (q !== 'onlyVals') {

                if (!field.multiple) {
                    if (field.withEmptyVal && field.withEmptyVal.trim() !== '' && field.category !== 'filter') {
                        optgroups[''].append($('<option>').data('content', field.withEmptyVal).text(""));
                    }
                }

                for (let i in LISTs.innerList) {
                    let iList = LISTs.innerList[i];
                    if (vals[iList] === 1) continue;
                    let v = LISTs.innerIndexed[iList];

                    if (!LISTs.isSliced) {
                        if (!isLikedFunc(v[0])) continue;
                    }

                    let opt = createOption(iList, v[0]);
                    let groupName = v[1] ? v[1] : '';

                    if (!optgroups[groupName]) {
                        optgroups[groupName] = $('<optgroup label="' + groupName + '">');
                    }
                    optgroups[groupName].append(opt);
                }
            }
            input.empty();

            Object.keys(optgroups).forEach(function (groupName) {
                input.append(optgroups[groupName]);
            });

            if (LISTs.isSliced === true) {
                let opt = createOption(0, 'Данные не полны. Воспользуйтесь поиском!');
                opt.prop('disabled', true);
                opt.css('text-align', 'center');
                input.append(opt);
            }

            input.selectpicker('refresh');
            input.selectpicker('val', checkedVal);
            return checkedVal
        };


        let iRenered = 0;
        const renderMe = function () {

            if(item[field.name] && item[field.name].replaceViewValue && LISTs.innerIndexed[item[field.name].v]){
                item[field.name].replaceViewValue(LISTs.innerIndexed[item[field.name].v]);
                delete item[field.name].replaceViewValue;
            }

            let td = input.closest('body');
            if (td && td.length) {
                divParent.find('.mark-loading').remove();
                let container = input.closest('.modal-body').length === 0 ? field.pcTable._container : input.closest('.modal-body');
                input.data('container', container);


                addValues(val);


                if (!input.data('is-rendered')) {

                    let searchTimeout;

                    input.data('selectpicker').$searchbox.off().on('click.dropdown.data-api focus.dropdown.data-api touchend.dropdown.data-api', function (e) {
                        e.stopPropagation();
                    });

                    let Q = '';

                    input.data('selectpicker').$searchbox.on('keyup', function (e) {
                        if (e.key === 'Escape') {
                            input.data('selectpicker').$button.click();
                            return true;
                        }

                        let q = $(this).val();
                        if (Q !== q) {
                            Q = q;
                            if (searchTimeout) {
                                clearTimeout(searchTimeout)
                            }
                            searchTimeout = setTimeout(function () {
                                if (LISTs.isListForLoad || LISTs.isSliced) {
                                    GetLoadListDeffered.call(field, q).then(function () {
                                        addValues.call(field, val, q);
                                    });
                                } else {
                                    addValues.call(field, val, q);
                                }
                            }, 750);
                        }
                    });


                    let $td = $(field).closest('td, .cell');

                    if (input.closest('.InsertRow, .InsertPanel').length === 0) {

                        let $selectContainer = input.data('container');

                        input.on('remove', function () {
                            $selectContainer.off('click.selectContainer.' + input.data('AppUin'));
                            $selectContainer.off('keydown.selectContainer.' + input.data('AppUin'));
                        });

                        $selectContainer.on('click.selectContainer.' + input.data('AppUin'), function (event) {
                            let target = $(event.target);
                            if (!target.closest('td').is('.editing') && !target.closest('.bootstrap-select').length) {
                                blurClbk(divParent, event)
                            }
                        });
                        $selectContainer.on('keydown.selectContainer.' + input.data('AppUin'), function (event) {

                            if (event.keyCode === 27) {
                                input.data('keyPressed', 'Esc');
                                escClbk(divParent, event);
                                return false;
                            }
                            if (event.keyCode === 13) {
                                input.data('enterPressed', true);

                            }

                            if (event.keyCode !== 9 && event.keyCode !== 16) {
                                $td.data('edited')
                            }
                            if (event.altKey || event.shiftKey) {
                                let key = event.altKey ? 'altKey' : (event.shiftKey ? 'shiftKey' : false);
                                input.data('keyPressed', key);
                            }

                        }).on('keyup', function (event) {
                            input.removeData('keyPressed');
                            input.removeData('enterPressed');
                        });
                        field.focusElement(divParent);
                    }

                    input.on('hidden.bs.select', function () {
                        let changed = input.data('changed');
                        let event = {};
                        let keyPressed = input.data('keyPressed');
                        if (keyPressed) event[keyPressed] = true;

                        if (!field.multiple) {
                            setTimeout(function () {
                                "use strict";
                                enterClbk(divParent, event)
                            }, 200);

                        }
                        else if (changed && input.closest('td.edt').length === 0) {
                            enterClbk(divParent, event);
                        }

                        input.data('changed', false);
                    });
                    input.on('show.bs.select', function () {

                        addValues(val);
                    });
                    input.on('shown.bs.select', function () {
                        let selectPicker = input.data('selectpicker');
                        selectPicker.$bsContainer.addClass('pcTable-selectpicker');
                        if (LISTs.isPreview) {
                            selectPicker.$bsContainer.addClass('select-with-preview');
                        }

                        if (selectPicker.$menuInner.height() < 100 && selectPicker.$menuInner.find('li').length > 6) {
                            selectPicker.$menuInner.height(300)
                        }

                        if (!selectPicker.cropped) {
                            selectPicker.cropped = true;
                            if (input.data('container').is('.pcTable-container')) {
                                selectPicker.$menuInner.height(selectPicker.$menuInner.height() - 4)
                            }
                        }
                    });
                    input.on('changed.bs.select', function () {
                        input.data('changed', true);
                        let oldVal = [];
                        if (val && val.forEach) {
                            val.forEach(function (v) {
                                oldVal.push(v);
                            });
                        }
                        val = input.val();


                        if (field.category === 'filter') {
                            if (field.multiple) {
                                let len = val.length;

                                if (oldVal.length > val.length) {
                                    if (val.length === 0) {
                                        val.push('*NONE*');
                                    }
                                } else {
                                    let newElement;
                                    val.some(function (el) {
                                        if (oldVal.indexOf(el) === -1) {
                                            newElement = el;
                                            return true;
                                        }
                                    });
                                    if (['*NONE*', '*ALL*'].indexOf(newElement) !== -1) {
                                        val = [newElement];
                                    } else {
                                        ['*NONE*', '*ALL*'].some(function (SpecialValue) {
                                            let SpecialIndex;
                                            if ((SpecialIndex = val.indexOf(SpecialValue)) !== -1) {
                                                val.splice(SpecialIndex, 1);
                                                return true;
                                            }
                                        })
                                    }
                                }

                                if (val.length !== len) {
                                    input.selectpicker('val', val);
                                }
                            }
                        }

                    });

                    input.on('remove', function () {
                        input.data('selectpicker').$bsContainer.remove();
                        input.data('container')
                            .off('keydown.selectContainer.' + input.data('AppUin'))
                            .off('click.selectContainer.' + input.data('AppUin'))
                    })
                }

            }
            else {
                if (iRenered < 50) {
                    setTimeout(function () {
                        renderMe(input, val)
                    }, iRenered * 10 + 1);
                    iRenered++;
                }
            }
        };


        if (LISTs.isListForLoad) {
            GetLoadListDeffered().then(function () {
                renderMe.call(field);
            });
        } else {
            renderMe();
        }


        return divParent;
    },
    getPanelText: function (fieldValue, td, item) {
        let field = this;
        let $div = $('<div>');
        let listVals = item[field['name']].v_;
        if (!field.multiple && item[field['name']].v_) {
            listVals = [item[field['name']].v_];
        }

        if (listVals) {
            $.each(listVals, function (k, val) {
                "use strict";
                let d = $('<div>').text(val[0]);

                if (val[1]) {
                    d.addClass('deleted_value')
                }
                else if (listVals.length !== 1) {
                    d.add('select-item');
                }
                $div.append(d);
            });
        }
        else {
            if (fieldValue === null || fieldValue === '') {
                if (field.withEmptyVal) return field.withEmptyVal;
                else return '';
            }

            let fieldValues = fieldValue;
            if (!field.multiple) {
                fieldValues = [fieldValues];
            }

            if (!fieldValues) fieldValues = [];

            if (field.list) {
                let vals = [];
                for (let i = 0; i < fieldValues.length; i++) {
                    vals[i] = fieldValues[i].toString();
                }

                for (let i = 0; i < field.list.length; i++) {
                    let v = field.list[i];
                    if (vals.indexOf(v[2].toString()) !== -1) {
                        let d = $('<span>').text(v[0])
                        if (v[1]) {
                            d.addClass('deleted_value')
                        } else if (vals.length !== 1) {
                            d.add('select-item');
                        }
                        $div.append(d);
                    }
                }
            }
        }
        return $div.children();
    },
    getCellText: function (fieldValue, td, item) {
        let field = this;
        let text = '';
        let $div = $('<div>');
        let listVals = item[field['name']].v_;
        if (!field.multiple && item[field['name']].v_) {
            listVals = [item[field['name']].v_];
        }

        if (listVals) {
            if (field.multiple && listVals.length > 1 && (field.multySelectView == 0)) {
                $div.append('<span class="select-item">' + listVals.length + ' элементов<span>');
            } else {
                if (listVals.length === 0) {
                    $div.append('<span class="select-item">' + this.getElementString(null) + '</span>');
                } else {
                    $.each(listVals, function (k, val) {
                        "use strict";
                        let d = $('<span>');
                        let id = null;
                        if (fieldValue) {
                            if (typeof fieldValue === 'object') {
                                id = fieldValue[k];
                            } else {
                                id = fieldValue;
                            }
                        }

                        d.text(field.getElementString(id, val));

                        if (val[1]) {
                            d.addClass('deleted_value')
                        }
                        else if (listVals.length !== 1) {
                            d.add('select-item');
                        }
                        $div.append(d);
                    });
                }
            }
        }
        else {
            if (fieldValue === null || fieldValue === '') {
                if (field.withEmptyVal) return field.withEmptyVal;
                else return '';
            }

            let fieldValues = fieldValue;
            if (!field.multiple) {
                fieldValues = [fieldValues];
            }

            if (!fieldValues) fieldValues = [];

            if (field.list) {
                let vals = [];
                for (let i = 0; i < fieldValues.length; i++) {
                    vals[i] = fieldValues[i].toString();
                }

                for (let i = 0; i < field.list.length; i++) {
                    let v = field.list[i];
                    if (vals.indexOf(v[2].toString()) !== -1) {
                        let d = $('<span>').text(field.getElementString(v[2], v));
                        if (v[1]) {
                            d.addClass('deleted_value')
                        } else if (vals.length !== 1) {
                            d.add('select-item');
                        }
                        $div.append(d);
                    }
                }
            }
        }
        return $div.children();
    },
    focusElement: function (div) {
        let button = div.find('button');
        let field = this;
        if (button.length == 0) {
            setTimeout(function () {
                field.focusElement(div)
            }, 50)
        }
        else
            button.focus();

    },
    isDataModified: function (edited, fromItem) {

        if ([null, ''].indexOf(edited) !== -1 && [null, ''].indexOf(fromItem) !== -1) return false;
        if ([null, ''].indexOf(edited) !== -1 || [null, ''].indexOf(fromItem) !== -1) return true;

        return !Object.equals(fromItem, edited);
    },
    checkIsFiltered: function (fieldVal, filters) {
        let val, check;
        if (this.multiple) {
            val = [];
            if (fieldVal && fieldVal.v_ && fieldVal.v_.length) {
                fieldVal.v_.forEach(function (v) {
                    val.push(v[0].hashCode().toString())
                })
            }
            check = function (v) {
                if (val.indexOf(v) !== -1) {
                    return true;
                }
            }
        } else {
            val = fieldVal.v_[0] === null ? 'null' : fieldVal.v_[0].toString();
            val = val.hashCode().toString();
            check = function (v) {
                if (v === val) {
                    return true;
                }
            }
        }
        return filters.some(check);
    },
    checkEditRegExp: function (val) {
        if (!this.warningEditRegExp) return true;
        try {
            if (this.multiple && Array.isArray(val)) {
                return val.some((v) => (new RegExp(this.warningEditRegExp)).test(val))
            }
            return (new RegExp(this.warningEditRegExp)).test(val);
        } catch (e) {
            return true;
        }
    },
    addDataToFilter: function (filterVals, valObj) {

        const addFiltersData = function (valObjElem) {
            let hash;
            let str = valObjElem[0];
            if (str === null) {
                hash = 'null'.hashCode();
            } else {
                hash = str.toString().hashCode();
                str = str.replace(/"/g, "&quot;");
            }
            filterVals[hash] = str;
        };
        if (this.multiple) {
            if (valObj && valObj.v_.length) {
                valObj.v_.forEach(function (valObj) {
                    addFiltersData(valObj);
                })
            }
        } else {
            addFiltersData(valObj.v_)
        }

    },
    getElementString: function (val, arrayVal) {
        "use strict";
        if (val === null || val === undefined) {
            if (!arrayVal || !arrayVal[0]) return this.withEmptyVal || '';
        }

        if (arrayVal[0] === null || arrayVal[0] === '') {

            return '[' + (this.withEmptyVal || '') + ']';
        }


        return arrayVal[0];
    }
};
fieldTypes.tree = {
    icon: 'fa-tree',
    FullView: false,
    getEditVal: function (div) {
        return div.data('val');
    },
    getEditElement: function ($oldInput, oldValueParam, item, enterClbk, escClbk, blurClbk, tabindex, editNow) {


        let field = this;
        let div = $oldInput || $('<div>');
        let dialog = div.data('dialog') || $('<div>').css('min-height', 200);
        div.data('dialog', dialog);
        let buttons, btn;

        oldValueParam = oldValueParam.v || '';

        let formFill = function (dlg) {
            field.treePanel.call(field, dialog, item, oldValueParam, enterClbk, escClbk);
        };

        const save = function (dlg, event, notEnter) {
            let checked_ids = [];
            let checked = dialog.data('jstree').jstree("get_selected", true);
            checked.forEach(function (node) {
                checked_ids.push(node.id);
            });

            if (!field.multiple) {
                checked_ids = checked_ids[0] || '';
            }

            div.data('val', checked_ids);
            if (!notEnter) {
                enterClbk(div, {});
                dlg.close();
            }
        };


        buttons = [];

        let btnsSave = {
            'label': "Сохранить",
            cssClass: 'btn-m btn-warning',
            action: save
        }, btnsClose = {
            'label': "Закрыть",
            cssClass: 'btn-m btn-default',
            'action': function (dialog) {
                escClbk(div, {});
                dialog.close();
            }
        };

        let title = '<b>' + (this.title) + '</b>';
        let eventName = 'ctrlS.commentdialog';
        const onshown = function (dialog) {
            dialog.$modalHeader.css('cursor', 'pointer');
            if (dialog.$modalBody.find('textarea').length === 0) {
                formFill(dialog);
            }

            dialog.$modalContent.css({
                width: $('body').width() * 0.8 > 800 ? 800 : $('body').width() * 0.8
            });

            $('body').on(eventName, function (event) {
                save(dialog, event, false);
            });

        };
        if (editNow) {
            let btnClicked = false;
            setTimeout(function () {

                let cdiv = div.closest('td').find('.cdiv');
                if (cdiv.length > 0) {
                    cdiv.data('bs.popover').options.content.find('.btn').each(function () {
                        btn = $(this);
                        let buttn = {};
                        buttn.label = btn.data('name');
                        buttn.cssClass = btn.attr('class').replace('btn-sm', 'btn-m');
                        buttn.icon = btn.find('i').attr('class');
                        buttn.save = btn.data('save');
                        buttn.click = btn.data('click');
                        buttn.action = function (dialog) {
                            if (buttn.save) {
                                save(dialog, {}, true);
                            }
                            buttn.click({});
                            btnClicked = true;
                            dialog.close();
                        };

                        buttons.push(buttn)
                    });
                    cdiv.popover('destroy');
                } else {
                    buttons.push(btnsSave);
                    buttons.push(btnsClose)
                }

                BootstrapDialog.show({
                    message: dialog,
                    type: null,
                    title: title,
                    buttons: buttons,
                    cssClass: 'fieldparams-edit-panel',
                    draggable: true,
                    onhide: function (dialog) {
                        $('body').off(eventName);
                        if (!btnClicked) {
                            blurClbk(div, {});
                        }
                    },
                    onshown: function (dialog) {
                        dialog.$modalContent.position({
                            of: $('body'),
                            my: 'top+50px',
                            at: 'top'
                        });
                    },
                    onshow: onshown

                });


            }, 1);


            div.text('Редактирование в форме').addClass('edit-in-form');
        } else {
            let showned = false;
            div.off().on('focus click', 'button', function () {
                if (showned) return false;
                showned = true;
                let buttonsClick = buttons.slice(0);
                buttonsClick.push(btnsSave);
                buttonsClick.push(btnsClose);

                var div = $(this).closest('div');
                BootstrapDialog.show({
                    message: dialog,
                    type: null,
                    cssClass: 'fieldparams-edit-panel',
                    title: title,
                    buttons: buttonsClick,
                    draggable: true,
                    size: BootstrapDialog.SIZE_WIDE,
                    onhide: function (event) {
                        showned = false;
                        $('body').off(eventName);
                        escClbk(div, event);
                    },
                    onshow: onshown
                })
            });

            if (div.find('button').length === 0) {
                btn = $('<button class="btn btn-default btn-sm text-edit-button">').text('Редактирование в форме');
                if (tabindex) btn.attr('tabindex', tabindex);

                div.append(btn);
            }

        }
        return div.data('val', oldValueParam);//.attr('data-category', category).attr('data-category', category);

    },
    treePanel: function ($treeblock, item, oldValueParam, enterClbk, escClbk, loadingFunc) {
        let field = this;

        let plugins = ["themes", 'json_data', 'search'];//, 'massload'
        if (field.multiple) {
            plugins.push('checkbox');
        }
        let $search = $('<div class="tree-search"><input class="form-control" type="text"></div>');
        let $searchInput = $search.find('input');
        setTimeout(function () {
            $searchInput.focus()
        }, 200);
        let $mes = $('<div></div>');


        let closed = 0;
        let to = false;
        let pastSearch = "";

        $searchInput.keyup(function () {
            if (to) {
                clearTimeout(to);
            }
            to = setTimeout(function () {

                if ($mes.closest('body').length) {
                    let v = $searchInput.val();
                    if (pastSearch !== v) {
                        pastSearch = v;
                        $mes.jstree(true).search(v, closed === 0);
                    }
                }
            }, 750);
        });


        $treeblock.append($search).append($mes);
        $treeblock.data('jstree', $mes);

        if (!this.multiple && this.withEmptyVal) {
            $mes.on("click", 'li.jstree-node[aria-selected="true"]', function (e) {
                // let node = $mes.jstree(true).get_node($(this).attr('id'));
                // console.log(node);
                $mes.jstree(true).deselect_node($(this));
                return false;
            })

        }


        $mes.on("init.jstree", function (e, data) {
            data.instance.settings.checkbox.cascade = '';
        }).jstree({
            "search": {
                "show_only_matches": true,
                "case_insensitive": true,
                "show_only_matches_children": true,
                search_callback: function (q, title) {
                    if (!title) return false;

                    let qs = q.toLowerCase().replace('ё', 'е').split(" ");
                    let text = title.text.toLowerCase().replace('ё', 'е');

                    return !qs.some(function (q) {
                        return text.indexOf(q) === -1
                    })
                },
                'ajax': function (q, cd) {
                    var self = this;
                    field.getEditSelect(item, q, null).then(function ($data) {
                        cd.call(self, $data[0])
                    });
                },
            },
            "massload": function (ids, cd) {
                var self = this;
                closed -= 1;
                field.getEditSelect(item, "", ids).then(function ($data) {
                    Object.values($data[0]).forEach(function (children) {
                        children.forEach(function (v) {
                            if (v.children === true) {
                                closed += 1;
                            }
                        });
                    });

                    cd.call(self, $data[0])
                });

            },
            'core': {
                'check_callback': true,
                "open_parents": true,
                'data': function (obj, cd) {
                    var self = this;
                    closed -= 1;
                    field.getEditSelect(item, "", obj.id == "#" ? null : obj.id).then(function ($data) {
                        $data[0].forEach(function (v) {
                            if (v.children === true) {
                                closed += 1;
                            }
                        });
                        cd.call(self, $data[0])
                    });
                },
                themes: {
                    "icons": false,
                    'name': 'default'
                }
            }
            , checkbox: {
                //  three_state: false,
            },
            "plugins": plugins
        });

        if (field.multiple) {
            if (field.category === "filter") {
                $mes.on("select_node.jstree", function (evt, data) {
                    if (["*ALL*", "*NONE*"].indexOf(data.node.id) !== -1) {
                        if (data.selected.length > 1) {
                            data.selected.forEach(function (nodeId) {
                                if (nodeId !== data.node.id) {
                                    data.instance.deselect_node($mes.jstree(true).get_node(nodeId));
                                }
                            });

                        }
                    } else {
                        ["*ALL*", "*NONE*"].forEach(function (nodeId) {
                            if (data.selected.indexOf(nodeId) !== -1) {
                                data.instance.deselect_node($mes.jstree(true).get_node(nodeId));
                            }
                        })

                    }

                })
            }
        }
    },
    getEditSelect: function (item, q, parentId) {
        let field = this;
        let d = $.Deferred();

        let itemTmp = {};
        Object.keys(item).forEach(function (k) {
            //Фильтруем jquery-объекты из item
            if (!/^\$/.test(k)) {
                if (k === 'id' || !(item[k] !== null && typeof item[k] === 'object' && Object.keys(item[k]).indexOf('v') !== -1)) {
                    itemTmp[k] = item[k];
                } else {
                    itemTmp[k] = item[k]['v'];
                }
            }
        });

        this.pcTable.model.getEditSelect(itemTmp, this.name, q, parentId, true).then(function (json) {
            let lists = [json.list, json.indexed];
            if (!field.codeSelectIndividual)
                field.list = lists;
            d.resolve(lists);
        });
        return d;
    },
    getPanelText: function (fieldValue, td, item) {
        this.FullView = true;
        let r = this.getCellText(fieldValue, td, item);
        delete this.FullView;
        return r;
    },
    getCellText: function (fieldValue, td, item) {
        let field = this;
        let arrayVals = item[field.name].v_;
        if (fieldValue) {
            if (field.multiple) {
                if (Array.isArray(fieldValue)) {
                    if (fieldValue.length === 0) return field.getElementSpan(null);
                    else if (fieldValue.length === 1) return field.getElementSpan(fieldValue[0], arrayVals[0]);
                    else {
                        if (field.multySelectView === "0" && !field.FullView) {
                            return $('<span class="select-item">' + fieldValue.length + ' эл.<span>')
                        } else {
                            let span = $('<span class="select-item">');
                            fieldValue.forEach((fVal, i) => span.append(field.getElementSpan(fVal, arrayVals[i])));
                            return span;
                        }
                    }
                } else {
                    return field.getElementSpan(fieldValue, [fieldValue, 0]);
                }
            } else return field.getElementSpan(fieldValue, arrayVals);
        } else return field.getElementString(null);

    },
    checkIsFiltered: function (fieldVal, filters) {
        let val, check;
        if (this.multiple) {
            val = [];
            if (fieldVal && fieldVal.v_ && fieldVal.v_.length) {
                fieldVal.v_.forEach(function (v) {
                    val.push(v[0].hashCode().toString())
                })
            }
            check = function (v) {
                if (val.indexOf(v) !== -1) {
                    return true;
                }
            }
        } else {
            val = fieldVal.v_[0] === null ? 'null' : fieldVal.v_[0].toString();
            val = val.hashCode().toString();
            check = function (v) {
                if (v === val) {
                    return true;
                }
            }
        }
        return filters.some(check);
    },
    addDataToFilter: function (filterVals, valObj) {

        const addFiltersData = function (valObjElem) {
            let hash;
            if (valObjElem[0] === null) {
                hash = 'null'.hashCode();
            } else {
                hash = valObjElem[0].toString().hashCode();
            }
            filterVals[hash] = valObjElem[0].replace(/"/g, "&quot;");
        };
        if (this.multiple) {
            if (valObj && valObj.v_.length) {
                valObj.v_.forEach(function (valObj) {
                    addFiltersData(valObj);
                })
            }
        } else {
            addFiltersData(valObj.v_)
        }

    },
    getElementSpan: function (val, arrayVal) {
        let span = $('<span>');
        if (val !== null) {
            span.text(this.getElementString(val, arrayVal));
            if (arrayVal[1] === 1) {
                span.addClass('deleted_value')
            }
        }
        return span;
    },
    getElementString: function (val, arrayVal) {
        "use strict";
        if (val === null || val === undefined) {
            if (!arrayVal || !arrayVal[0]) return this.withEmptyVal || '';
        }

        if (arrayVal[0] === null || arrayVal[0] === '') {

            return '[' + (this.withEmptyVal || '') + ']';
        }
        if (this.FullView) {
            return arrayVal[2] || arrayVal[0];
        }
        return arrayVal[0];
    }
};
fieldTypes.json = {

    __addInput: function (fName, f, oldValue) {
        var f = f || {};
        var type = f.type || typeof oldValue[fName];
        var width = 12;
        if (type == 'checkbox') width = 3;
        if (f.width) width = f.width;
        var input = $('<div class="field form-group">').attr('data-name', fName).addClass('col-sm-' + width);


        var element;

        switch (type) {
            case 'string':
                element = $('<input>').val(oldValue[fName] ? oldValue[fName] : (f.default ? f.default : ''));
                break;
            case 'json':
                element = $('<div class="JSONEditor">').height(300);
                var editor = new JSONEditor(element.get(0), {});
                var btn = $('<a href="#">editText</a>').on('click', function () {
                    var div = $('<div>');
                    var textarea = $('<textarea class="form-control" style="height: 250px;">').val(JSON.stringify(editor.get(), null, 2)).appendTo(div);
                    div.dialog({
                        title: 'Содержимое JSON-поля',
                        width: 500,
                        height: 600,
                        buttons: {
                            'Сохранить': function () {
                                editor.setText(textarea.val());
                                div.dialog('close')
                            },
                            'Закрыть': function () {
                                div.dialog('close')
                            }
                        }
                    });
                    return false;
                });
                element.find('.jsoneditor-menu').append(btn);

                element.data('editor', editor);
                editor.set(oldValue[fName] ? oldValue[fName] : (f.default ? JSON.parse(f.default) : {}));
                break;
            case 'html':
                element = $('<div class="HTMLEditor">').height(300);
                var el = $('<div>').appendTo(element);
                var editor = CodeMirror(el.get(0), {
                    value: (oldValue[fName] ? oldValue[fName] : (f.default ? f.default : "")),
                    mode: "text/html",
                    height: '250px',
                    readOnly: false,
                    theme: 'eclipse',
                    lineNumbers: true,
                    gutter: true,
                    indentWithTabs: true,
                    autoCloseTags: true
                });
                setTimeout(function () {
                    editor.refresh()
                }, 20);
                element.data('editor', editor);
                break;
            case 'integer':
                element = $('<input>').val(oldValue[fName] ? oldValue[fName] : (f.default ? f.default : "")).attr('type', 'number');
                if (f.min !== undefined) element.attr('min', f.min);
                if (f.max !== undefined) element.attr('max', f.max);
                if (f.step !== undefined) element.attr('step', f.step);
                break;
            case 'checkbox':
                element = $('<input>').attr('type', 'checkbox');
                if (oldValue[fName]) element.prop('checked', true);
                else if (oldValue[fName] === undefined) element.prop('checked', true);

                break;
            case 'select':
                element = $('<select>');
                if (f.values) {
                    $.each(f.values, function (k, v) {
                        element.append($('<option>').attr('value', k).text(v));
                    })
                }

                if (oldValue[fName]) element.val(oldValue[fName]);
                else if (oldValue[fName] === undefined && f.default) element.val(f.default);
                break;
        }
        if (type == 'checkbox') {
            input.prepend($('<label>').text(f.title ? f.title : fName).addClass('form-check-label'));
            if (element) {
                element.data('type', type)
                input.find('label').prepend(element);
            }
            // input.addClass('checkbox');
        }
        else {
            input.prepend($('<label>').text(f.title ? f.title : fName));
            if (element) element.data('type', type).addClass('form-control');
            input.append(element);
        }

        input.data('type', type);

        var button = $(' <span>*</span>');

        if (f.required) {
            button.addClass('text-danger');
        } else {
            button.text('');
            button.addClass('glyphicon glyphicon-remove remove');
            button.on('click', function () {
                var field = $(this).closest('.field');
                var name = field.data('name');
                field.remove();
                //TODO - not worked
                /* var elseFields=$(this).closest('.jsonForm').parent().find('.elseFields select');
                 elseFields.append('<option name="' +name+ '">' +name+ '</option>');*/
            })
        }


        input.find('label').after(button);
        return input;
    },

    getEditElement: function ($oldInput, oldValueParam, item, enterClbk, escClbk, blurClbk) {

        var field = this;
        var div = $('<div>');
        var dialog = $('<div>').css('min-height', 200);
        var buttons;
        var form = $('<div class="jsonForm row">').appendTo(dialog);
        div.data('form', form);
        div.data('field', this);


        var format = this.jsonFields;
        var oldValue = oldValueParam || {};

        if (typeof oldValue == 'string') oldValue = JSON.parse(oldValue);

        var addInput = function (fName) {
            var input = field.__addInput(fName, format[fName], oldValue);
            form.append(input);
        };

        var oldValueTmp = $.extend({}, oldValue);


        var emptyGroup = '';
        var elseFieldsLength = 0;
        var elseFields = {}
        elseFields[emptyGroup] = [];

        $.each(format, function (fName, fOpt) {
            if (fOpt.required == true || fOpt.showInForm == true) {
                addInput(fName);
                if (oldValueTmp[fName] !== undefined) delete oldValueTmp[fName];
            }
            else if (oldValueTmp[fName] === undefined) {
                var group = emptyGroup;
                if (fOpt.fGroup) {
                    group = fOpt.fGroup
                    if (!elseFields[fOpt.fGroup]) {
                        elseFields[fOpt.fGroup] = [];
                    }
                }
                elseFields[group].push(fName);
                elseFieldsLength++;
            }
        });

        $.each(oldValueTmp, function (k, v) {
            addInput(k)
        });

        if (elseFieldsLength) {
            var input = $('<div class="row elseFields">');
            element = $('<select class="selectpicker form-control dropup" data-size="5" data-title="--Выбрать поле--">');

            if (App.keys(elseFields).length == 1) {
                $.each(elseFields[App.keys(elseFields)[0]], function (k, fName) {
                    element.append($('<option>').attr('value', fName).text(format[fName].title ? format[fName].title : fName));
                });
            }
            else {
                $.each(elseFields, function (group, fields) {
                    var group = $('<optgroup label="' + group + '">');
                    $.each(fields, function (k, fName) {
                        group.append($('<option>').attr('value', fName).text(format[fName].title ? format[fName].title : fName));
                    });
                    element.append(group);
                })
            }


            input.prepend($('<label>').text('Добавить поле'));
            element.addClass('form-control');

            input.append(element);
            element.selectpicker('render');
            element.on('change', function () {
                var f = $(this).val();
                element.find('option[value="' + f + '"]').remove();
                addInput(f);
            });

            dialog.append(input.wrap('<div style="padding:10px">').parent());
        }


        buttons = {

            "Сохранить": function () {

                var obj = {};
                var fullJSONEditor = form.find('.fullJSONEditor');
                if (fullJSONEditor.length == 1) {
                    obj = fullJSONEditor.data('editor').get();
                }
                else {

                    form.find('input, select, textarea, .JSONEditor, .HTMLEditor').not('.JSONEditor *').not('.HTMLEditor *').each(function () {
                        var element = $(this);
                        var nameField = element.closest('.field').data('name');


                        switch (element.closest('.field').data('type')) {
                            case "array":
                                try {
                                    obj[nameField] = element.data('editor').get();
                                    if (!$.isArray(obj[nameField])) {
                                        throw 'Ошибка структуры поля';
                                    }
                                }
                                catch (err) {
                                    App.notify('Ошибка структуры поля ' + nameField);
                                    throw 'Ошибка структуры поля';
                                }
                                break;
                            case "object":
                            case "json":
                                try {
                                    obj[nameField] = element.data('editor').get();
                                    if (typeof obj[nameField] !== "object") {
                                        throw 'Ошибка структуры поля';
                                    }
                                }
                                catch (err) {
                                    App.notify('Ошибка структуры поля ' + nameField);
                                    throw 'Ошибка структуры поля';
                                }
                                break;
                            case "html":
                                obj[nameField] = element.data('editor').getValue();
                                break;
                            case "checkbox":
                            case "boolean":
                                obj[nameField] = element.is(':checked') ? true : false;
                                break;
                            case "integer":
                                obj[nameField] = parseInt(element.val());
                                break;
                            default:
                                obj[nameField] = element.val();
                                break;
                        }


                    });
                }
                div.data('val', JSON.stringify(obj));

                enterClbk(div, event);
                dialog.remove();
            },
            "Закрыть": function () {
                dialog.dialog('close');
            }, "Редактор": function () {
                var height = form.height();
                var element = $('<div class="fullJSONEditor">').height(height + 100);
                var editor = new JSONEditor(element.get(0), {});
                editor.set(oldValue)
                var btn = $('<a href="#">editText</a>').on('click', function () {
                    var div = $('<div>');
                    var textarea = $('<textarea class="form-control" style="height: 250px;">').val(JSON.stringify(editor.get(), null, 2)).appendTo(div);
                    div.dialog({
                        title: 'Содержимое JSON-поля',
                        width: 500,
                        height: height + 100,
                        buttons: {
                            'Сохранить': function () {
                                editor.setText(textarea.val());
                                div.dialog('close')
                            },
                            'Закрыть': function () {
                                div.dialog('close')
                            }
                        }
                    });
                    return false;
                });
                form.empty().append(element);
                form.next().empty();
                element.data('editor', editor);
                element.find('.jsoneditor-menu').append(btn);
            }
        };

        if (item.id) {
            dialog.dialog({
                title: (item && item.id ? item.id : '') + ' ' + this.title
                , width: 700
                , modal: true
                , close: function (event) {
                    escClbk(div, event);
                    dialog.remove();
                },
                buttons: buttons
            });
            div.text('Редактирование в форме').addClass('edit-in-form');
        }
        else {
            div.on('focus click', 'button', function () {
                var div = $(this).closest('div');
                dialog.dialog({
                    title: field.title || field.name
                    , width: 700
                    , modal: true
                    , close: function (event) {
                        escClbk(div, event);
                        dialog.remove();
                    },

                    buttons: buttons
                });
            });
            div.append($('<button class="btn btn-default">').text(oldValueParam ? oldValueParam : 'Редактирование'))
        }

        return div.data('val', oldValueParam);

    },
    getEditVal: function (input) {
        return input.data('val');
    },
    getCellText: function (val) {
        return JSON.stringify(val);
    },
    focusElement: function (div) {
        var button = div.find('button');
        var field = this;
        if (button.length === 0) {
            setTimeout(function () {
                field.focusElement(div)
            }, 50)
        }
        else
            button.focus();

    }
    /*,
     getCellText: function(fieldValue){
     return $('<div>').text(fieldValue).height(30).css('textOverflow', 'ellipsis')
     }*/
};
fieldTypes.fieldParams = $.extend({}, fieldTypes.json, {
    icon: 'fa-code',
    isPanelField: true,
    isDataModified: function (newVal, oldVal) {
        return !Object.equals(oldVal, newVal);
    },
    getEditElement: function ($oldInput, oldValueParam, item, enterClbk, escClbk, blurClbk, tabindex, editNow) {


        let field = this;
        let div = $('<div>');
        let dialog = $('<div>').css('min-height', 200);
        let buttons;
        let form = $('<div class="jsonForm">').appendTo(dialog);
        div.data('form', form);
        div.data('field', this);

        let formFill = function (oldValueParam) {

            let jsonFields = field.jsonFields;

            field.getValue(oldValueParam, item, !editNow).then(function (json) {
                let oldValue = json.value;

                if (typeof oldValue === 'string') oldValue = JSON.parse(oldValue);
                let oldValueTmp = $.extend({}, oldValue);


                let addInput = function (fName, fieldsList, fieldsLevelFuncs) {
                    let fieldSettings = jsonFields.fieldSettings[fName];
                    if (!fieldSettings) return false;

                    if (fieldSettings['categories']) {
                        if (fieldSettings['categories'].indexOf(item['category']['v']) === -1) return false;
                    }


                    let thisValue = {};
                    if (oldValueTmp[fName] === undefined || oldValueTmp[fName].isOn === undefined) {
                        thisValue.isOn = oldValueTmp[fName] !== undefined;
                        thisValue.Val = oldValueTmp[fName];
                        if (thisValue.Val === undefined) {
                            thisValue.Val = fieldSettings.default;
                        }
                        if (!fieldSettings.parent && fieldSettings.type === 'checkbox' && thisValue.Val === true) {
                            thisValue.isOn = true;
                        }
                        oldValueTmp[fName] = thisValue;
                    } else {
                        thisValue = oldValueTmp[fName];
                    }

                    if (fName === 'codeSelect') {
                        let treeSelectDefault = '=: selectRowListForTree(table: \'\'; field: \'\'; order: \'\' asc; where:  \'\' = ; parent: \'\'; disabled:)';
                        if (form.find('div[data-name="type"] select').val() === "tree") {
                            if (thisValue.Val === fieldSettings.default)
                                thisValue.Val = treeSelectDefault;
                        } else {
                            if (thisValue.Val === treeSelectDefault)
                                thisValue.Val = fieldSettings.default;
                        }
                    }


                    let isHidden = false;

                    let parentSettings = jsonFields.fieldSettings[fieldSettings.parent];
                    let parentValue = oldValueTmp[fieldSettings.parent];


                    if (fieldSettings.parent && fieldsList.indexOf(fieldSettings.parent) !== -1) {
                        if (!parentValue || parentValue.isOnCheck !== true) {
                            isHidden = true;
                        }
                    }
                    thisValue.changed = function () {
                        "use strict";
                        if (this.isOnCheck === true) {
                            form.find('[data-parent="' + fName.toLowerCase() + '"]').show();
                        } else {
                            form.find('[data-parent="' + fName.toLowerCase() + '"]').hide().find('input[type="checkbox"]').prop('checked', false).trigger('change');
                        }
                    };
                    let divInput = field.__addInput.call(field, fName, fieldSettings, thisValue, item);
                    form.append(divInput);
                    if (fieldSettings.parent) {
                        divInput.attr('data-parent', fieldSettings.parent.toLowerCase());
                        if (isHidden) {
                            divInput.hide();
                        }
                    }

                    let level = fieldsLevelFuncs[fName]();

                    divInput.css('padding-left', level * 19);

                    return divInput;
                };


                let fieldType = 'string';
                if (oldValueTmp.type) {
                    fieldType = oldValueTmp.type.Val || oldValueTmp.type;
                }

                let editorsRefresh = function () {
                    dialog.find('.codeEditor, .HTMLEditor').each(function () {
                        if ($(this).data('editor')) {
                            $(this).data('editor').refresh();
                        }
                    });

                };

                function addfields(fieldType) {
                    let fieldsList;
                    form.empty();
                    if (item.table_id.v === '2' && (item.name.v === 'data_src' || item.name.v === 'data')) {
                        if (item.name.v === 'data') {
                            fieldsList = ['width', 'showInWeb'];
                        } else {
                            fieldsList = ['width', 'jsonFields', 'showInWeb', 'editable', 'insertable', 'required', 'logging', 'default', 'copyOnDuplicate'];
                        }
                        fieldType = 'fieldParams';
                    } else if (item.table_name && item.table_name.v === 'tables_vidgets' && (item.name.v === 'data_src' || item.name.v === 'data')) {
                        if (item.name.v === 'data') {
                            fieldsList = ['width', 'showInWeb'];
                        } else {
                            fieldsList = ['width', 'jsonFields', 'showInWeb', 'editable', 'insertable', 'required', 'logging', 'default', 'copyOnDuplicate'];
                        }
                        fieldType = 'fieldParams';
                    } else {
                        fieldsList = jsonFields.fieldListParams[fieldType];
                    }

                    let fieldsLevelFuncs = {
                        type: function () {
                            return 0;
                        }
                    };
                    fieldsList.forEach(function (fieldName) {
                        let fSettings = jsonFields.fieldSettings[fieldName];
                        if (!fSettings.parent || fieldsList.indexOf(fSettings.parent) === -1) {
                            fieldsLevelFuncs[fieldName] = function () {
                                return 0;
                            };
                        } else {
                            fieldsLevelFuncs[fieldName] = function () {
                                return fieldsLevelFuncs[fSettings.parent]() + 1;
                            };
                        }

                    });

                    oldValueTmp['type'] = {
                        isOn: true,
                        Val: fieldType
                    };


                    let typeInput = addInput('type', fieldsList, fieldsLevelFuncs).find('select');

                    typeInput.on('change', function () {
                        addfields($(this).val())
                    });

                    fieldsList.forEach(function (fName) {
                        addInput(fName, fieldsList, fieldsLevelFuncs);
                    });

                    editorsRefresh();

                }

                addfields(fieldType);
            })
        };

        const save = function (dialog) {

            var obj = {};
            var fullJSONEditor = form.find('.fullJSONEditor');
            if (fullJSONEditor.length === 1) {
                obj = fullJSONEditor.data('editor').get();
            } else {

                form.find('input, select, textarea, .JSONEditor, .HTMLEditor, .codeEditor').not('.JSONEditor *').not('.HTMLEditor *').not('.codeEditor *').not('input[data-type="switcher"]').each(function () {
                    var element = $(this);
                    let val;
                    let field = element.closest('.field')
                    var nameField = field.data('name');
                    switch (element.closest('.field').data('type')) {
                        case 'code':
                            val = element.data('editor').getValue();
                            break;
                        case "json":
                            try {
                                val = element.data('editor').get();
                                if (typeof val !== "object") {
                                    throw 'Ошибка структуры поля';
                                }
                            } catch (err) {
                                App.notify('Ошибка структуры поля ' + nameField);
                                throw 'Ошибка структуры поля';
                            }
                            break;
                        case "html":
                            val = element.data('editor').getValue();
                            break;
                        case "checkbox":
                            val = !!element.is(':checked');
                            break;
                        case "integer":
                            val = parseInt(element.val());
                            break;
                        default:
                            val = element.val();
                            break;
                    }

                    let isOn;
                    isOnCheckbox = field.find('input[data-type="switcher"]');
                    if (isOnCheckbox.length === 0) {
                        isOn = element.is(':visible');
                    } else {
                        isOn = isOnCheckbox.is(':checked');
                    }
                    obj[nameField] = {
                        isOn: isOn,
                        Val: val
                    }
                });
            }
            div.data('val', obj);

            enterClbk(div, event);
            dialog.close();
        };

        buttons = [
            {
                'label': "Сохранить",
                cssClass: 'btn-m btn-warning',
                action: save
            }, {
                'label': "Закрыть",
                cssClass: 'btn-m btn-default',
                'action': function (dialog) {
                    dialog.close();
                }
            }
        ];

        let eventName = 'ctrlS.FieldParams';

        if (editNow) {
            window.top.BootstrapDialog.show({
                message: dialog,
                type: BootstrapDialog.TYPE_DANGER,
                title: 'Параметры поля <b>' + (item.title.v) + '</b>',
                buttons: buttons,
                cssClass: 'fieldparams-edit-panel',
                draggable: true,
                onhide: function (event) {
                    escClbk(div, event);
                    $('body').off(eventName);
                },
                onshown: function (dialog) {
                    formFill(oldValueParam.v);
                    dialog.$modalContent.position({
                        of: $('body'),
                        my: 'top+50px',
                        at: 'top'
                    })
                },
                onshow: function (dialog) {
                    dialog.$modalHeader.css('cursor', 'pointer')
                    dialog.$modalDialog.width(1000);
                    $('body').on(eventName, function (event) {
                        save(dialog);
                    });
                }

            });


            div.text('Редактирование в форме').addClass('edit-in-form');
        } else {
            let clicked = false;
            div.on('focus click', 'button', function () {
                if (clicked) return;
                clicked = true;

                var div = $(this).closest('div');
                window.top.BootstrapDialog.show({
                    message: dialog,
                    type: BootstrapDialog.TYPE_DANGER,
                    cssClass: 'fieldparams-edit-panel',
                    title: 'Параметры поля <b>' + (item.title.v) + '</b>',
                    buttons: buttons,
                    draggable: true,
                    size: BootstrapDialog.SIZE_WIDE,
                    onhide: function (event) {
                        escClbk(div, event);
                        $('body').off(eventName);
                        clicked = false;
                    },
                    onshown: function (dialog) {
                        dialog.$modalDialog.width(1000);
                        dialog.$modalHeader.css('cursor', 'pointer')
                        formFill(oldValueParam.v);
                        $('body').on(eventName, function (event) {
                            save(dialog);
                        });
                    }
                })
            });

            let btn = $('<button class="btn btn-danger btn-sm text-edit-button">').text('Редактировать параметры');
            if (tabindex) btn.attr('tabindex', tabindex);

            div.append(btn);

        }
        return div.data('val', oldValueParam);//.attr('data-category', category).attr('data-category', category);

    },
    __addInput: function (fName, f, Val, item) {
        let field = this;
        var f = f || {};
        var type = f.type;
        let oldValue;
        let isOn;

        oldValue = Val.Val;
        isOn = Val.isOn;


        var input = $('<div class="field form-group">').attr('data-name', fName);


        var element;


        switch (type) {
            case 'code':

                element = $('<div class="codeEditor">');
                var el = $('<div>').appendTo(element);
                var editor = CodeMirror(el.get(0), {
                    value: (oldValue ? oldValue : (f.default ? f.default : "")),
                    mode: "totum",
                    height: '150px',
                    readOnly: false,
                    theme: 'eclipse',
                    lineNumbers: true,
                    gutter: false,
                    indentWithTabs: true
                });
                editor.getScrollerElement().style.minHeight = '150px';
                element.data('editor', editor);
                editor.table = item.table_name && item.table_name.v ? item.table_name.v : null;
                break;

            case 'string':
                element = $('<input>').val(oldValue !== undefined ? oldValue : (f.default ? f.default : ''));
                break;
            case 'json':
                element = $('<div class="JSONEditor">').height(300);
                var editor = new JSONEditor(element.get(0), {});
                var btn = $('<a href="#" style="padding-top: 5px; display: inline-block; padding-left: 20px;">Вручную</a>').on('click', function () {
                    var div = $('<div>');
                    var textarea = $('<textarea class="form-control" style="height: 250px;">').val(JSON.stringify(editor.get(), null, 2)).appendTo(div);

                    BootstrapDialog.show({
                        message: div,
                        type: null,
                        title: 'Ручное изменение json-поля',
                        buttons: [
                            {
                                'label': "Сохранить",
                                cssClass: 'btn-m btn-warning',
                                action: function (dialog) {
                                    try {
                                        editor.setText(textarea.val());
                                        dialog.close();
                                    } catch (e) {
                                        App.modal('Ошибка формата JSON')
                                    }
                                }
                            }, {
                                'label': "Закрыть",
                                cssClass: 'btn-m btn-default',
                                'action': function (dialog) {
                                    dialog.close();
                                }
                            }
                        ],
                        cssClass: 'fieldparams-edit-panel',
                        draggable: true,
                        onhide: function (event) {
                            // escClbk(div, event);
                        },
                        onshown: function (dialog) {
                            dialog.$modalContent.position({
                                of: window
                            });
                        },
                        onshow: function (dialog) {
                            dialog.$modalHeader.css('cursor', 'pointer')
                            dialog.$modalContent.css({
                                width: 500
                            });
                        }

                    });
                    return false;
                });
                element.find('.jsoneditor-menu').append(btn);

                element.data('editor', editor);
                let ov = (typeof oldValue === 'string' ? JSON.parse(oldValue) : oldValue);
                editor.set(ov);
                break;
            case 'html':
                element = $('<div class="HTMLEditor">')
                var el = $('<div>').appendTo(element);
                var editor = CodeMirror(el.get(0), {
                    value: (oldValue ? oldValue : (f.default ? f.default : "")),
                    mode: "text/html",
                    height: '150px',
                    readOnly: false,
                    theme: 'eclipse',
                    lineNumbers: true,
                    indentWithTabs: true,
                    autoCloseTags: true
                });
                editor.getScrollerElement().style.minHeight = '150px',

                    element.data('editor', editor);
                break;
            case 'integer':
                element = $('<input>').val(oldValue !== undefined ? oldValue : (f.default ? f.default : "")).attr('type', 'number');
                if (f.min !== undefined) element.attr('min', f.min);
                if (f.max !== undefined) element.attr('max', f.max);
                if (f.step !== undefined) element.attr('step', f.step);
                break;
            case 'checkbox':
                element = $('<input>').attr('type', 'checkbox').data('type', type);
                if (oldValue) element.prop('checked', true);
                //else if (oldValue === undefined) element.prop('checked', true);

                break;
            case 'select':
                element = $('<select>');
                if (f.values) {
                    let icons = f.valIcons || {};
                    let orders;
                    if (f.valuesOrder) {
                        orders = f.valuesOrder.slice(0);
                    }
                    if (fName === 'type') {
                        if (item.id === 355) {
                            orders = ["fieldParams"]
                        }
                        $.each(orders, function (i, k) {
                            if (fieldTypes[k]) {
                                icons[k] = 'fa ' + fieldTypes[k].icon;
                            }
                        });
                    }

                    const addOption = function (k, v) {
                        let option = $('<option>').attr('value', k).text(v);
                        if (icons[k]) {
                            option.data('icon', icons[k]);
                        }
                        element.append(option);
                    };
                    if (orders) {
                        orders.forEach(function (valName) {
                            addOption(valName, f.values[valName])
                        })
                    } else {
                        $.each(f.values, addOption)

                    }

                }

                if (f.multiple) {
                    element.attr('multiple', 'multiple');
                    element.attr('size', '2');
                }
                setTimeout(function () {
                    element.selectpicker();
                }, 20);


                if (oldValue) element.val(oldValue);
                else if (oldValue === undefined && f.default) element.val(f.default);
                break;

        }
        let $switcher;
        Val.isOnCheck = true;

        if (type === 'checkbox') {
            input.prepend($('<label class="field-param-lable">').text(f.title ? f.title : fName).addClass('form-check-label').prepend(element).append('<a href="http://docs.totum.online/3.shtml#fp-' + fName + '" target="_blank"><i class="fa fa-question-circle-o"></i></a>'));
            input.addClass('checkbox');
            $switcher = element;
            if (!element.is(':checked')) {
                Val.isOnCheck = false;
                input.addClass('disabled');
            }
        } else {
            input.prepend($('<label class="field-param-lable">').text(f.title ? f.title : fName).append('<a href="http://docs.totum.online/3.shtml#fp-' + fName + '" target="_blank"><i class="fa fa-question-circle-o"></i></a>'));

            if (element) element.data('type', type);
            if (!element.data('editor')) element.addClass('form-control');
            input.append(element);

            if (f.required !== true) {
                $switcher = $('<input type="checkbox" data-type="switcher"/>');

                if (isOn) {
                    Val.isOnCheck = true;
                    $switcher.prop('checked', true);
                } else {
                    Val.isOnCheck = false;
                    input.addClass('disabled');
                }

                input.find('label').prepend($switcher);
                input.addClass('checkbox');
            }
        }

        if ($switcher) {
            $switcher.on('change', function () {
                if ($(this).is(':checked')) {
                    if (Val.isOnCheck !== true) {
                        Val.isOnCheck = true;
                        input.removeClass('disabled');
                        Val.changed();
                    }
                } else {
                    if (Val.isOnCheck !== false) {
                        Val.isOnCheck = false;
                        input.addClass('disabled');
                        Val.changed();
                    }
                }
            });
        }


        input.data('type', type);
        return input;
    },
    getValue: function (value, item, isModulPanel) {
        "use strict";
        if (isModulPanel) {
            let def = $.Deferred();
            def.resolve({'value': (value || {})});
            return def;
        }
        let data = {'fieldName': this.name};
        if (item.id) {
            data['rowId'] = item.id;
        }
        return this.pcTable.model.getValue(data, this.table_id);
    },
    getPanelText: function (fieldvalue) {
        let data = {};
        return JSON.stringify(fieldvalue, null, 2);
        /*
        Object.keys(fieldvalue).forEach(function (k) {
            let v = fieldvalue[k];
            if (v.isOn) data[k] = v.Val;
        });
        return JSON.stringify(data, null, 2);*/
    },
    getCellText: function (fieldValue) {
        return 'Настройки поля';
    }
})
;
fieldTypes.button = {
    icon: 'fa-hand-pointer-o',
    getCellText: function (fieldValue, td, item) {
        let field = this, format={};
        if (this.category === 'column') {
            if (item.id){
                format = $.extend({}, (field.pcTable.f || {}), (item.f || {}), (item[field.name].f || {}));
            }else{
                format.block = true;
            }
        }else{
            format = $.extend({}, (field.pcTable.f || {}), (item[field.name].f || {}));
        }
        if (format.block || (!this.pcTable.control.editing && !this.pressableOnOnlyRead)){
            let btn =  $('<button class="btn btn-default btn-xxs button-field" tabindex="-1" disabled>').text(this.buttonText || 'Кнопочка');

            if(format.text){
                btn.text(format.text)
            }
            if(format.comment){
                let i;
                i = $('<i class="cell-icon fa fa-info"></i>');
                btn.prepend(i);
                i.attr('title', format.comment)
            }
            else if (format.icon){
                btn.prepend('<i class="cell-icon fa fa-'+format.icon+'"></i>');
            }

            return btn.wrap('<span class="cell-value">').parent()
        }


        if (td) {
            td.addClass('cell-button');
        }
        let btn = $('<button class="btn btn-default btn-xxs button-field" tabindex="-1">').text(this.buttonText || 'Кнопочка');

        if(format.text){
            btn.text(format.text)
        }
        if(format.comment){
            let i;
            i = $('<i class="cell-icon fa fa-info"></i>');
            btn.prepend(i);
            i.attr('title', format.comment)
        }
        else if (format.icon){
            btn.prepend('<i class="cell-icon fa fa-'+format.icon+'"></i>');
        }


        return btn;
    },
    btnOK: function ($td) {
        let btn = $td.find('button');
        let field = this;
        btn.text('Выполнено');
        $td.data('clicked', true);

        setTimeout(function () {
            $td.removeData('clicked');
            let item=field.pcTable._getItemBytd.call(field.pcTable, $td);
            btn.replaceWith(field.getCellText.call(field, item[field.name], $td, item));
        }, BUTTONS_TIMEOUT)
    }
};
fieldTypes.link = {
  icon: 'fa-link'
};
fieldTypes.comments = {
    icon: 'far fa-comments-o',
    getEditVal: function (div) {
        return div.data('val');
    },
    getCellText: function (fieldValue) {
        if (fieldValue.n === 0 || !fieldValue.n) return '';
        let field = this;

        let mainDiv = $('<span>');
        let div = $('<span class="comments">').text(fieldValue.c[0] + ' ' + fieldValue.c[1] + ': ' + fieldValue.c[2]).appendTo(mainDiv);

        if (fieldValue.notViewed) {
            div.addClass('notViewed');
            if (field.decorationColor){
                div.css('border-bottom-color', field.decorationColor);
            }
        }

        return mainDiv;
    },
    getValue: function (value, item, isModulPanel) {
        "use strict";
        let field = this;
        let def = $.Deferred();
        if (isModulPanel) {
            if (!value) value = [];
            def.resolve({'value': value});
        }
        else if (value.n === 0 || (value.n === 1 && !value.cuted && !value.notViewed)) {
            if (!value) value = [];
            def.resolve({'value': [value.c]});

        } else {
            let data = {'fieldName': this.name};
            if (item.id) {
                data['rowId'] = item.id;
            }
            def = this.pcTable.model.getValue(data, this.table_id);
        }
        def.then(function (json) {
            if (item[field.name].v.notViewed || item[field.name].notViewed){
                let $_def = $.Deferred();
                $_def.then(function () {
                    delete item[field.name].v.notViewed;
                    delete item[field.name].notViewed;
                    let td;
                    if (item.id){
                        td=field.pcTable._getTdByFieldName(field.name, field.pcTable.data[item.id].$tr);
                    }else{
                        td=field.pcTable._paramsBlock.find('td[data-field="'+field.name+'"]');
                        if (!td.length){
                            td=field.pcTable._footersBlock.find('td[data-field="'+field.name+'"]')
                        }
                    }
                    if (td && td.length){
                        td.find('.notViewed-num').remove();
                        td.find('.notViewed').removeClass('notViewed');
                    }
                });
                if (item[field.name].notViewed){
                    field.pcTable.model.setCommentsViewed(item[field.name].v.length, field.name, item.id).then(function () {
                        $_def.resolve();
                    });
                }else{
                    $_def.resolve();
                }

            }
        });

        return def;
    },
    getPanelText: function (fieldValue, td, item) {
        let field = this;
        let def = $.Deferred();
        this.getValue(fieldValue, item, false).then(function (json) {
            let div = $('<div class="comments">');

            $.each(json.value, function (i, com) {
                div.append(field.getCommentLine(com));
            });
            def.resolve(div);

        }).fail(function () {
            def.reject();
        });

        return def.promise();
    },
    getCommentLine: function (com) {
        let div = $('<div class="comments-line">');
        div.append($('<span class="com_dt">').text(com[0]));
        div.append(' ');
        div.append($('<span class="com_author">').text(com[1]));
        div.append(' ');
        div.append($('<span class="com_text">').html(App.textWithLinks(com[2])));

        return div;
    },
    getPanelVal(val, div) {
        if (div) return this.getEditVal(div);
    },
    getEditElement: function ($oldInput, oldValueParam, item, enterClbk, escClbk, blurClbk, tabindex, editNow) {


        let field = this;
        let div = $oldInput || $('<div>');
        let dialog = div.data('dialog') || $('<div>').css('min-height', 200);
        div.data('dialog', dialog);
        let buttons;

        oldValueParam = oldValueParam.v || '';

        let formFill = function (dlg) {
            field.getValue.call(field, oldValueParam, item, !editNow).then(function (json) {
                let $input = $('<textarea type="text" style="height:90px;resize: vertical" class="form-control"/>');
                $.each(json.value, function (i, com) {
                    dialog.append(field.getCommentLine(com));
                });
                dialog.append($('<div class="comments-input">').append($input));
                dialog.data('input', $input);
                $input.focus();
            });

        };

        const save = function (dlg, event, notEnter) {
            div.data('val', dialog.find('textarea').val().trim());
            if (!notEnter) {
                enterClbk(div, {});
                dlg.close();
            }
        };


        buttons = [];

        let btnsSave = {
            'label': "Сохранить",
            cssClass: 'btn-m btn-warning',
            action: save
        }, btnsClose = {
            'label': "Закрыть",
            cssClass: 'btn-m btn-default',
            'action': function (dialog) {
                escClbk(div, {});
                dialog.close();
            }
        };

        let title = 'Комментарии поля <b>' + (this.title) + '</b>';
        let eventName = 'ctrlS.commentdialog';

        if (editNow) {
            let btnClicked = false;
            setTimeout(function () {
                let cdiv = div.closest('td').find('.cdiv');
                if (cdiv.length > 0) {
                    cdiv.data('bs.popover').options.content.find('.btn').each(function () {
                        let btn = $(this);
                        let buttn = {};
                        buttn.label = btn.data('name');
                        buttn.cssClass = btn.attr('class').replace('btn-sm', 'btn-m');
                        buttn.icon = btn.find('i').attr('class');
                        buttn.save = btn.data('save');
                        buttn.click = btn.data('click');
                        buttn.action = function (dialog) {
                            if (buttn.save) {
                                save(dialog, {}, true);
                            }
                            buttn.click({});
                            btnClicked = true;
                            dialog.close();
                        };

                        buttons.push(buttn)
                    });
                    cdiv.popover('destroy');
                } else {
                    buttons.push(btnsSave);
                    buttons.push(btnsClose)
                }

                BootstrapDialog.show({
                    message: dialog,
                    type: null,
                    title: title,
                    buttons: buttons,
                    cssClass: 'fieldparams-edit-panel',
                    draggable: true,
                    onhide: function (dialog) {
                        $('body').off(eventName);
                        if (!btnClicked) {
                            blurClbk(div, {});
                        }
                    },
                    onshown: function (dialog) {
                        dialog.$modalContent.position({
                            of: $('body'),
                            my: 'top+50px',
                            at: 'top'
                        });
                        formFill(dialog);
                    },
                    onshow: function (dialog) {
                        dialog.$modalHeader.css('cursor', 'pointer');
                        dialog.$modalContent.css({
                            width: 900
                        });

                        $('body').on(eventName, function (event) {
                            save(dialog, event, false);
                        });
                    }

                });


            }, 1);


            div.text('Редактирование в форме').addClass('edit-in-form');
        }
        else {
            let showned = false;
            div.off().on('focus click', 'button', function () {
                if (showned) return false;
                showned= true;
                let buttonsClick = buttons.slice(0);
                buttonsClick.push(btnsSave);
                buttonsClick.push(btnsClose);

                var div = $(this).closest('div');
                BootstrapDialog.show({
                    message: dialog,
                    type: null,
                    cssClass: 'fieldparams-edit-panel',
                    title: title,
                    buttons: buttonsClick,
                    draggable: true,
                    size: BootstrapDialog.SIZE_WIDE,
                    onhide: function (event) {
                        showned = false;
                        $('body').off(eventName);
                        escClbk(div, event);
                    },
                    onshown: function (dialog) {
                        dialog.$modalHeader.css('cursor', 'pointer');
                        if (dialog.$modalBody.find('textarea').length === 0) {
                            formFill(dialog);
                        }

                        dialog.$modalContent.css({
                            width: 900
                        });

                        $('body').on(eventName, function (event) {
                            save(dialog, event, false);
                        });

                    }
                })
            });

            if (div.find('button').length === 0) {
                let btn = $('<button class="btn btn-default btn-sm text-edit-button">').text('Добавить комментарий');
                if (tabindex) btn.attr('tabindex', tabindex);

                div.append(btn);
            }

        }
        return div.data('val', oldValueParam);//.attr('data-category', category).attr('data-category', category);

    },

};

    $.each(fieldTypes, function (k, v) {
        fieldTypes[k] = $.extend({}, defaultField, v);
    });

    App.pcTableMain = function (element, config) {

        config = $.extend({},
            {
                tableRow: {},
                nSorted: true,
                isCreatorView: false,
                withCsvButtons: false,
                withCsvEditButtons: false,
                noDataRowClass: 'pcTable-noDataRow',
                contanerClass: 'pcTable-container',
                tableClass: 'pcTable-table',
                width: null,
                checkIsUpdated: 0,
                _containerId: '',
                scrollWrapper: null,
                tableWidth: 0,
                control: {
                    adding: false,
                    sorting: false,
                    deleting: false,
                    duplicating: false
                },
                dataSorted: [],
                data: [],
                data_params: null,
                dataSortedVisible: [],
                mainFieldName: 'id',

                insertRow: null,
                _container: null,
                _content: null,

                extraClastersBottom: null,
                extraClastersTop: null,
                dataSortedClasters: {'t': [], 'm': [], 'b': []},
                ScrollClasterized: null,

                _innerContainer: null,
                _header: null,
                _table: null,

                LogButton: null,

                model: null,
                fields: {},
                hidedFields: [],
                fieldCategories: {
                    column: [],
                    params: [],
                    footer: []
                },
                sorted: {
                    field: '',
                    direction: 'asc'
                },
                _sorting: {},
                _filterable: false,
                filtersClearButton: null,
                _indexes: {
                    fieldByName: {}
                }
            }, config);
        $.extend(this, config, true);

        this.hidden_fields = this.hidden_fields || {};
        if (this.hidden_fields.length === 0) this.hidden_fields = {};


        if (element) {
            this.refreshArraysFieldCategories(true);
            let $element = $(element);
            $element.data(pcTable_DATA_KEY, this);
            this._container = $element;
            this._containerId = this._container.attr('id');
            if (!this._containerId) {
                this._containerId = 'pcTable' + (pcTableIds++);
                this._container.attr('id', this._containerId)
            }
            this._init();
            this.render(config.addVars);
        } else {
            this.initForPanel(config)
        }


        this.model.addPcTable(this);
        //if (this.tableRow.type === 'tmp') {}
        return this;
    };

let panelId = 0;
window.EditPanel = function (pcTable, dialogType, inData, isElseItems) {

    if(window.top!==window) return  window.top.EditPanel.call(window.top, pcTable, dialogType, inData, isElseItems);

    let data = $.extend(true, {}, inData);

    let EditPanelFunc = this;
    EditPanelFunc.pcTable = pcTable;
    EditPanelFunc.panelId = 'panel' + (panelId++);

    let $d = $.Deferred();

    this.$panel = $('<div class="InsertPanel">');
    this.isNewPanel = true;
    this.blockedFields = {};
    this.bootstrapPanel = null;


    let checkMethod = data.id ? 'checkEditRow' : 'checkInsertRow';
    let saveMethod = data.id ? 'saveEditRow' : 'add';
    this.panelType = data.id ? 'edit' : 'insert';
    this.editItem = data || {};
    $('body').trigger('pctable-opened', {'type': 'panel'});

    EditPanelFunc.resolved = false;

    this.checkRow = function (field, isFirstCall) {

        EditPanelFunc.pcTable.model[checkMethod](this.getDataForPost(), field).then(function (json) {
            EditPanelFunc.editRow.call(EditPanelFunc, json);
        });
    };
    this.saveRow = function (panel, btn) {
        EditPanelFunc.pcTable.model[saveMethod](this.getDataForPost())
            .then(function (json) {
                $d.resolve(json);
                EditPanelFunc.resolved = true;
                $(window.top.document.body).trigger('pctable-closed', {
                    'type': 'panel',
                    json: json,
                    method: EditPanelFunc.panelType,
                    tableId: EditPanelFunc.pcTable.tableRow.id
                });
                EditPanelFunc.bootstrapPanel.close();
            }).fail(function () {
            if (btn.length && btn.isAttached()) {
                let btn = panel.$modal.find('.btn-save').prop('disabled', false);
            }
        })
    };

    this.editRow = function (json) {
        "use strict";
        let isAnyEditableFields = false;
        EditPanelFunc.pcTable.f = json.f || {};
        EditPanelFunc.editItem.f = json.row.f || {};

        EditPanelFunc.pcTable.fieldCategories.column.forEach(function (field, index) {

            if (field.name === 'n') return;

            let cell = EditPanelFunc.$panel.find('div.cell[data-field-name="' + field.name + '"]');

            let Oldval = EditPanelFunc.editItem[field.name];
            EditPanelFunc.editItem[field.name] = json.row[field.name];

            if (EditPanelFunc.isEditable(field)) isAnyEditableFields = true;

            let format = $.extend({}, (EditPanelFunc.pcTable.f || {}), (EditPanelFunc.editItem.f || {}), (EditPanelFunc.editItem[field.name].f || {}));

            if (cell.length) {

                if (cell.data('input') && !format.block) {
                    if (!Oldval || field.isDataModified(EditPanelFunc.editItem[field.name].v, Oldval.v) || field.codeSelectIndividual) {
                        EditPanelFunc.createCell.call(EditPanelFunc, cell, field, index, format);
                    }
                } else {
                    EditPanelFunc.createCell.call(EditPanelFunc, cell, field, index, format)
                }
            } else {

                EditPanelFunc.$panel.append(cell = EditPanelFunc.createCell.call(EditPanelFunc, cell, field, index, format));

                let divWrapper = cell.wrap('<div class="cell-wrapper" style="position: relative">').parent().width(390);
                let label = $('<label style="margin-right: -25px">').text(field.title || field.name).prependTo(divWrapper);

                if (field.unitType) {
                    label.text(label.text() + ', ' + field.unitType);
                }
                if (field.linkToSelectTable) {
                    divWrapper.append(' <a href="' + field.linkToSelectTable.link + '" class="color-primary-primary" style="font-size: 12px" target="_blank">' + field.linkToSelectTable.title + '</a> ')
                }
            }
            if (format.hideinpanel) {
                cell.parent().css('display', 'none');
            } else {
                cell.parent().css('display', '');
            }
            cell.attr('data-field-name', field.name);

            if (EditPanelFunc.panelType === 'edit' && Object.keys(EditPanelFunc.editItem[field.name]).indexOf('c') !== -1) {
                let button = cell.find('button.handled');
                if (!button.length) {
                    button = $('<button class="btn btn-sm pull-right btn-default handled" style="position: absolute; right: 0px;" tabindex="' + (index * 2 + 2) + '"></button>');
                    cell.prepend(button).css('padding-right', 35).css('position', 'relative');
                    if (EditPanelFunc.isEditable(field)) {
                        button.on('click', function () {
                            if (EditPanelFunc.editItem[field.name].h === true) {
                                EditPanelFunc.editItem[field.name].h = false;
                            } else {
                                EditPanelFunc.editItem[field.name].h = true;
                            }
                            EditPanelFunc.checkRow.call(EditPanelFunc);
                        });
                        button.prop('disabled', false);
                    } else {
                        button.prop('disabled', 'disabled');

                        if (!EditPanelFunc.editItem[field.name].h) {
                            button.remove();
                        }

                    }
                }
                button.removeClass('btn-warning').addClass('btn-default');
                button.html('<i class="fa fa-hand-grab-o"/>');
                if (EditPanelFunc.editItem[field.name].h) {
                    button.addClass('btn-warning').removeClass('btn-default');
                    if (EditPanelFunc.editItem[field.name].c !== EditPanelFunc.editItem[field.name].v) {
                        button.html('<i class="fa fa-hand-paper-o"/>');
                    }
                }
            }

        }, EditPanelFunc);

        if (EditPanelFunc.isNewPanel) {
            let title = '';
            if (this.panelType === 'edit') {
                let itemName;
                if (EditPanelFunc.pcTable.tableRow.main_field && EditPanelFunc.pcTable.fields[EditPanelFunc.pcTable.tableRow.main_field]) {
                    let fieldName = EditPanelFunc.pcTable.tableRow.main_field;
                    itemName = (EditPanelFunc.editItem[fieldName] || json.row[fieldName]);
                    if (itemName) itemName = itemName.v;
                    itemName = ' "' + itemName + '"';
                }
                if (!itemName) {
                    itemName = 'id ' + (EditPanelFunc.editItem['id'] || json.row.id);
                } else {
                    itemName = 'id ' + (EditPanelFunc.editItem['id'] || json.row.id) + ' ' + itemName;
                }

                if (!EditPanelFunc.pcTable.control.editing || (json.row.f.block && !isAnyEditableFields)) {
                    title = 'Просмотр <b> ' + itemName + '</b> таблицы <b>' + EditPanelFunc.pcTable.tableRow.title + '</b>';
                } else {
                    title = 'Редактирование <b> ' + itemName + '</b> таблицы <b>' + EditPanelFunc.pcTable.tableRow.title + '</b>';
                }
            } else {
                title = 'Добавление строки в таблицу <b>' + EditPanelFunc.pcTable.tableRow.title + '</b>';
            }

            EditPanelFunc.cleateBootstrapPanel.call(EditPanelFunc, title, dialogType, (EditPanelFunc.type === 'insert' || isAnyEditableFields));
            EditPanelFunc.isNewPanel = false;
        }

    };


    this.cleateBootstrapPanel = function (title, type, isEditable) {
        let EditPanel = this;
        let buttons = [];

        if (isEditable) {

            buttons.push({
                action: function (panel, event) {
                    "use strict";
                    let btn = panel.$modal.find('.btn-save').prop('disabled', 'disabled');
                    setTimeout(function () {
                        EditPanelFunc.pcTable.model.doAfterProcesses(function () {
                            EditPanel.saveRow.call(EditPanel, panel, btn);

                        });
                    }, 250)
                },
                cssClass: 'btn-warning btn-save',
                label: 'Cохранить'
            });
        }

        if (isElseItems) {

            buttons.push({
                action: function (panel) {

                    panel.close();
                },
                label: 'Закрыть'
            });

        }

        buttons.push({
            action: function (panel) {
                $d.resolve(undefined, /*next*/true);
                EditPanelFunc.resolved = true;

                panel.close();
            },
            label: 'Закрыть' + (isElseItems === true ? ' →' : '')
        });

        EditPanel.bootstrapPanel = BootstrapDialog.show({
            type: type || null,
            size: BootstrapDialog.SIZE_WIDE,
            message: EditPanel.$panel,
            cssClass: 'edit-row-panel',
            title: title,
            buttons: buttons,
            draggable: true,
            onhidden: function () {
                $d.resolve();
                if (!EditPanelFunc.resolved) {
                    $(window.top.document.body).trigger('pctable-closed', {'type': 'panel'});
                }
                $(window.top.document.body).off('.' + EditPanelFunc.panelId);
            },
            onshown: function (dialog) {
                "use strict";
                dialog.indexedButtons[Object.keys(dialog.indexedButtons)[0]].attr('tabindex', 500);
                if (Object.keys(dialog.indexedButtons).length === 2) {
                    dialog.indexedButtons[Object.keys(dialog.indexedButtons)[1]].attr('tabindex', 501);
                }
            }

        })
    };

    this.createCell = function (cell, field, fieldIndex, format) {
        let item = EditPanelFunc.editItem || {};
        item[field.name] = item[field.name] || {};

        if (cell.length === 0) {
            cell = $("<div>").addClass('cell');
            if (field.code) {
                cell.addClass('with-code');
            }
        }

        if (!this.isEditable(field)) {
            EditPanelFunc.blockedFields[field.name] = true;

            let span = $('<span class="' + (field.type !== 'button' ? 'form-control no-borders' : 'link') + '">')
                .append(field.getCellTextInPanel.call(field, (EditPanelFunc.editItem[field.name].v), cell, EditPanelFunc.editItem, EditPanelFunc.pcTable));

            if (field.unitType) {
                span.append(' ' + field.unitType);
            }

            if (field.type === 'button' && EditPanelFunc.pcTable) {
                cell.on('click', function () {
                    EditPanelFunc.pcTable._buttonClick.call(EditPanelFunc.pcTable, cell, field, item);
                });
            }

            cell.html(span).data('input', null);

            /* if (format.color) cell.css('color', format.color);
             if (format.bold) cell.css('font-weight', 'bold');
             if (format.decoration) cell.css('text-decoration', format.decoration);
             if (format.italic) cell.css('font-style', 'italic');*/

            return cell;
        }

        EditPanelFunc.blockedFields[field.name] = false;

        let getEditVal = function ($input) {
            let editVal;
            try {
                editVal = field.getEditVal($input);
            } catch (error) {
                App.popNotify(error, $input, 'default');
                return null;
            }
            return editVal;
        };

        let onAction = false;

        let inFocus = cell.find('input,button').is(':focus');


        let saveClbck = function ($input, event, onBlur) {
            onAction = true;

            let editValResult = getEditVal($input);

            if (editValResult === null) {

                EditPanelFunc.FocusIt.call(EditPanelFunc, 0, fieldIndex);

            } else {
                if (!onBlur) {

                    EditPanelFunc.FocusIt.call(EditPanelFunc, 0, fieldIndex + 1);
                }
                if (field.isDataModified(editValResult, EditPanelFunc.editItem[field.name].v)) {
                    EditPanelFunc.editItem[field.name].v = editValResult;
                    if (field.code && !field.codeOnlyInAdd) {
                        EditPanelFunc.editItem[field.name].h = true;
                    }
                    if (field.isPanelField === true) {
                        EditPanelFunc.createCell.call(EditPanelFunc, cell, field, 50 + fieldIndex);
                    }
                    EditPanelFunc.checkRow.call(EditPanelFunc, field.name);
                }
            }
            onAction = false;
        };
        let blurClbck = function ($input, event) {
            setTimeout(function () {
                if ($input && $input.length && $input.isAttached()) {
                    if (onAction) {
                        onAction = false;
                    } else {
                        saveClbck($input, event, true);
                    }
                }

            }, 40);
            return false;
        };
        let escClbck = function ($input, event) {
            onAction = true;

            let editValResult = getEditVal($input);
            if (field.isDataModified(editValResult, EditPanelFunc.editItem[field.name].v)) {
                EditPanelFunc.checkRow.call(EditPanelFunc);
            }
        };


        let input = field.getEditElement(cell.data('input'), EditPanelFunc.editItem[field.name], EditPanelFunc.editItem, saveClbck, escClbck, blurClbck, 50 + fieldIndex);

        if (!input.isAttached()) {
            cell.html(input)
        }
        cell.data('input', input);

        if (inFocus) {
            field.focusElement(input);
        }

        if (field['type'] === 'select' && field.changeSelectTable) {

            let sourseBtnClick = function () {
                let ee = {};
                $.each(EditPanelFunc.editItem, function (k, v) {
                    if (k.substring(0, 1) !== '$') {
                        ee[k] = v;
                    }
                });
                let isAdd = $(this).is('.source-add');
                if (isAdd) {
                    ee[field.name] = null;
                }
                let opened = 0;
                $(window.top.document.body)
                    .on('pctable-opened.select-' + EditPanelFunc.panelId, function () {
                        opened++;
                    })
                    .on('pctable-closed.select-' + EditPanelFunc.panelId, function (event, data) {
                        opened--;
                        let isAdded = (data && /*data.tableId === field.selectTableId &&*/ data.method === 'insert' && data.json && data.json.chdata && data.json.chdata.rows);
                        const refreshInputAndPage = function () {
                            if (opened === 0 || isAdded) {
                                let inputOld = input;
                                delete field.list;
                                if (inputOld.data('input').data('LISTs')) {
                                    inputOld.data('input').data('LISTs').isListForLoad = true;
                                }
                                if (isAdded) {
                                    if (field.multiple) {
                                        EditPanelFunc.editItem[field.name].v.push(Object.keys(data.json.chdata.rows)[0]);
                                    } else {
                                        EditPanelFunc.editItem[field.name].v = Object.keys(data.json.chdata.rows)[0];
                                    }
                                }

                                inputOld.replaceWith(input = field.getEditElement(null, EditPanelFunc.editItem[field.name], EditPanelFunc.editItem, saveClbck, escClbck, blurClbck));
                                cell.data('input', input);
                                EditPanelFunc.checkRow.call(EditPanelFunc, field.name);


                                if (!isAdd && EditPanelFunc.pcTable.isMain) {
                                    EditPanelFunc.pcTable.model.refresh(function (json) {
                                        EditPanelFunc.pcTable.table_modify.call(EditPanelFunc.pcTable, json);
                                    })
                                }
                                $('body').off('.select-' + EditPanelFunc.panelId);
                            }
                        };
                        setTimeout(refreshInputAndPage, 100);//Чтобы успело открыться окошко слещующей панели, если оно есть
                    });
                EditPanelFunc.pcTable.model.selectSourceTableAction(field.name, ee)
            };

            let selectBtns = $('<div class="selectBtns">');
            cell.prepend(selectBtns);
            let btn = $('<button class="btn btn-default-primary btn-sm"><i class="fa fa-edit"></i></button>');
            selectBtns.prepend(btn);
            btn.on('click', sourseBtnClick);

            if (field.changeSelectTable === 2) {
                cell.addClass('with-source-add-button');
                let btn = $('<button class="btn btn-default-primary btn-sm source-add"><i class="fa fa-plus"></i></button>');
                selectBtns.append(btn);
                btn.on('click', sourseBtnClick);
            } else {
                cell.addClass('with-source-edit-button');
            }
        }

        return cell;
    };
    this.getDataForPost = function () {
        let data = {};
        if (EditPanelFunc.editItem.id) {
            data.id = EditPanelFunc.editItem.id;
        }
        EditPanelFunc.pcTable.fieldCategories.column.forEach(function (f) {
            if (f.name === 'n') return;

            if (EditPanelFunc.editItem[f.name] && EditPanelFunc.isEditable(f)) {
                data[f.name] = {};
                let value = null;
                if (EditPanelFunc.panelType === 'edit') {
                    data[f.name].v = EditPanelFunc.editItem[f.name].v;
                    if (f.code && !f.codeOnlyInAdd) {
                        data[f.name].h = EditPanelFunc.editItem[f.name].h || false;
                    }
                    data[f.name].v = f.getPanelVal.call(f, data[f.name].v, EditPanelFunc.$panel.find('div.cell[data-field-name="' + f.name + '"]').data('input'));
                } else {
                    data[f.name] = EditPanelFunc.editItem[f.name].v;
                    data[f.name] = f.getPanelVal.call(f, data[f.name], EditPanelFunc.$panel.find('div.cell[data-field-name="' + f.name + '"]').data('input'));
                }

            } else if (inData[f.name]) {
                if (EditPanelFunc.panelType === 'insert') {
                    data[f.name] = inData[f.name].v;
                }
            }
        });
        return data;
    };
    this.isEditable = function (field) {

        if (!EditPanelFunc.pcTable.control.editing) return false;
        let format = $.extend({}
            , (EditPanelFunc.pcTable.f || {})
            , (EditPanelFunc.editItem.f || {})
            , (EditPanelFunc.editItem[field.name].f || {})
        );

        if (format.block === true) return false;
        if (this.panelType === 'insert') return field.insertable;

        return field.editable;
    };
    this.FocusIt = function (outTimed, focusIndex) {

        if (outTimed === 0) {
            setTimeout(function () {
                EditPanelFunc.FocusIt.call(EditPanelFunc, 1, focusIndex);
            }, 50);
            return false;
        }

        let isLastCell = true;


        if (!EditPanelFunc.$panel || !EditPanelFunc.$panel.length) return false;

        EditPanelFunc.pcTable.fieldCategories.column.forEach(function (field, index) {
            if (field.name === 'n') return;

            if (focusIndex === index) {
                if (!EditPanelFunc.isEditable(field)) {
                    ++focusIndex;
                    return;
                } else {
                    let input = EditPanelFunc.$panel.find('.cell:eq(' + index + ')').data('input');
                    if (input) {
                        field.focusElement(input);
                    }
                }
                isLastCell = false;
                return false;
            }
        });
        if (isLastCell) {
            let buttonSave = EditPanelFunc.bootstrapPanel.indexedButtons[Object.keys(EditPanelFunc.bootstrapPanel.indexedButtons)[0]];
            buttonSave.focus();
        }
    };


    if (typeof EditPanelFunc.pcTable !== 'object') {
        App.getPcTableById(EditPanelFunc.pcTable).then(function (pcTable) {
            EditPanelFunc.pcTable = pcTable;
            EditPanelFunc.checkRow.call(EditPanelFunc, null, true);
        });
    } else {
        App.getPcTableById(EditPanelFunc.pcTable[0], {sess_hash: EditPanelFunc.pcTable[1]}).then(function (pcTable) {
            pcTable.model.setDataRows(EditPanelFunc.pcTable[2]);
            EditPanelFunc.pcTable = pcTable;
            EditPanelFunc.checkRow.call(EditPanelFunc, null, true);
        });
    }

    return $d.promise();
};
$.extend(App.pcTableMain.prototype, {
    addScrollsRules: function () {
        let pcTable = this;
        this._innerContainer
            .append(this._table)
            .on('scroll', function () {
                "use strict";
                pcTable._removeEditCell();
            });

        if (!pcTable.withoutScrolls) {
            $(function () {
                pcTable._container.niceScroll({
                    cursorwidth: 7,
                    mousescrollstep: 90,
                    mousescroll: 190,
                    autohidemode: false,
                    enablekeyboard: false,
                    cursoropacitymin: 1,
                    railoffset: {left: -3}
                });

                /* pcTable._innerContainer.niceScroll({
                     cursorwidth: 7,
                     mousescrollstep: 190,
                     mousescroll: 190,
                     autohidemode: false,
                     enablekeyboard: false,
                     enablemousewheel: false,
                     cursoropacitymin: 1,
                     railoffset: {top:18},
                     cursorcolor: '#d4d4d4'
                 });*/
            });
        }

    },
    Scroll: function () {
        let pcTable = this;
        let self = {};
        let firstOffset = pcTable._content.offset().top;

        self.table=undefined;

        let heights = {
            all: 0,
            top: 0,
            content: 0,
            bottom: 0
        };

        let table;
        let last_cluster = false,
            scroll_debounce = 0,
            pointer_events_set = false,//?
            createTableHeadRow = function () {
                if (!self.table) {
                    self.table = $('<table>').appendTo(pcTable._innerContainer);
                    self.table.width(pcTable.tableWidth);
                    self. table.append($('table.pcTable-table thead').clone(true)).attr('class', $('table.pcTable-table').attr('class'));

                    self.table.find('th:not(.id) div, th:not(.id) .btn').remove();
                    self.table.find('th.id .pcTable-filters button[data-action="checkbox"]').remove();
                    self.table.find('th').removeClass('with-filter');
                    let $topButton = $('<div class="btn btn-default btn-xxs "><i class="fa fa-arrow-up"></i></div>')
                        .on('click', function () {
                            pcTable._container.scrollTop(pcTable._filtersBlock.offset().top - pcTable.scrollWrapper.offset().top);
                        });

                    /* let orderButton = pcTable._innerContainer.find('th.id').find('.fa-sort').parent().clone(true).on('click', function () {
                         setTimeout(function () {
                             table.remove();
                             table = undefined;
                             createTableHeadRow()
                         }, 50);
                     });*/

                    self.table.find('th.id .pcTable-filters:first').append($topButton);

                    if (self.table.find('th.n').length) {
                        let saveButton = pcTable._innerContainer.find('th.n').find('i.fa-save').parent().clone(true);
                        self.table.find('th.n').append($('<div class="pcTable-filters">').append(saveButton));
                    }

                }
                self.table.css({
                    position: 'absolute',
                    top: parseInt($('#table').offset().top) - parseInt($('.innerContainer').offset().top)
                });

                /*pcTable._content.off('scroll');
                pcTable._content.on('refreshed.scroll', function () {
                    if (refreshed) {
                        clearTimeout(refreshed);
                    }
                    refreshed = setTimeout(createTableHeadRow, 20);
                })*/
            },
            scrollFunc = function () {


                /*  if (table) {
                      table.remove();
                      table = undefined;
                  }*/


                let scrollTop = getScrollTop();
                if (last_cluster != (last_cluster = self.getClusterNum(scrollTop))) {
                    self.insertToDOM(last_cluster);
                }

                let tableInner = $('table.pcTable-table');
                let tableInnerHead = tableInner.find('thead');
                if (tableInnerHead.height() + tableInner.offset().top - $('#table').offset().top <= 0) {
                    if (!self.table) {


                        createTableHeadRow();

                    } else {
                        self.table.css({top: parseInt($('#table').offset().top) - parseInt($('.innerContainer').offset().top)})
                    }
                } else {
                    if (self.table) {
                        self.table.remove();
                        self.table = undefined;
                        pcTable._content.off('scroll')
                    }
                }


            },
            getScrollTop = function () {
                try {
                    return pcTable._content.offset().top;
                } catch (e) {
                    return 0;
                }

            };


        pcTable._container.on('scroll', function () {
            clearTimeout(scroll_debounce);
            scroll_debounce = setTimeout(function () {
                scrollFunc.call(pcTable);
            }, 50);
        });
        let cache = {
            top_offset: 0,
            bottom_offset: 0,
            rows: $()
        };
        $.extend(self, {
            rows_in_block: 4,
            item_height: pcTABLE_ROW_HEIGHT,
            getClusterNum: function (scrollTop) {
                let num;
                if (scrollTop >= 0) num = 0;
                else {
                    num = Math.floor(-scrollTop / this.block_height)
                }

                return Math.max(num, 0);
            },
            reloadScrollHead: function () {
                if (self.table) {
                    self.table.remove();
                    self.table = undefined;
                }
                scrollFunc.call(pcTable);
            },
            generate: function (cluster_num) {
                let rows = pcTable.dataSortedVisible;
                let rows_len = rows.length;
                if (rows_len < this.rows_in_block) {
                    return {
                        top_offset: 0,
                        bottom_offset: 0,
                        rows: rows
                    }
                }
                let items_start = Math.max((this.rows_in_block * cluster_num), 0),
                    items_end = items_start + this.rows_in_cluster + 3 * this.rows_in_block,
                    top_offset = Math.max(items_start * this.item_height, 0),
                    bottom_offset = Math.max((rows_len - items_end) * this.item_height, 0),
                    this_cluster_rows = [];


                for (let i = items_start; i < items_end; i++) {
                    rows[i] && this_cluster_rows.push(rows[i]);
                }

                return {
                    top_offset: top_offset,
                    conteinerHeight: pcTable._content.height(),
                    i_start: items_start,
                    bottom_offset: bottom_offset,
                    cluster_num: cluster_num,
                    rows: this_cluster_rows
                }
            },
            getRowsHeight: function () {
                if (pcTable.dataSortedVisible.length === 0) return;
                this.block_height = this.item_height * this.rows_in_block;
                this.rows_in_cluster = Math.floor((window.innerHeight - pcTable._container.offset().top) / this.item_height);
            },
            insertToDOM: function (cluster, forceCheckTableHeight, forceRefreshData) {
                // explore row's height

                if (!this.rows_in_cluster) {
                    this.getRowsHeight();
                }

                if (!cluster) cluster = this.getClusterNum(getScrollTop());

                let data = this.generate(cluster),
                    this_cluster_rows = data.rows.join(',');
                if (forceRefreshData || this.checkChanges('data', this_cluster_rows, cache)) {
                    this.setHtml(data.rows, data.top_offset, data.bottom_offset, forceRefreshData);
                }
                if (forceCheckTableHeight) {
                    // pcTable.scrollWrapper.height(pcTable.scrollWrapperStartHeight + pcTable.dataSortedVisible.length * this.item_height+100);
                    pcTable._container.getNiceScroll().resize();
                }

            },
            setHtml: function (rows, top, bottom, forceRefreshData) {
                let height = 0;
                pcTable._content.find('.editing').each(function (element) {
                    pcTable._removeEditing.call(pcTable, element);
                });
                let $trs = pcTable._content.empty().get(0);
                if (top) $trs.appendChild($('<tr style="height: ' + top + 'px;" class="loading-row"><td colspan="' + (pcTable.fieldCategories.column.length + 1) + '"></td></tr>').get(0));

                if (pcTable.dataSorted.length === 0) {
                    $trs.appendChild(pcTable._createNoDataRow().get(0));
                } else if (pcTable.dataSortedVisible.length === 0) {
                    $trs.appendChild(pcTable._createNoDataRow('По условиям фильтрации не выбрана ни одна строка').get(0));
                } else {
                    for (let i in rows) {
                        let id = rows[i];
                        let item = pcTable.data[id];
                        if (!item.$tr || forceRefreshData) {
                            pcTable._createRow.call(pcTable, item);
                        }
                        item.$tr.data('item', item);
                        $trs.appendChild(item.$tr.get(0));
                    }
                }
                if (bottom) $trs.appendChild($('<tr style="height: ' + bottom + 'px;" class="loading-row"><td colspan="' + (pcTable.fieldCategories.column.length + 1) + '"></td></tr>').get(0));
            },
            checkChanges: function (type, value, cache) {
                let changed = value != cache[type];
                cache[type] = value;
                return changed;
            }
        });

        return self;
    }
});

$.extend(App.pcTableMain.prototype, {
    sort: function (field, sortDirection) {
        let pcTable = this;
        let fieldName = field.name;

        let isNumber = field.type === 'number';
        let data = pcTable.data;

        if (!(pcTable.nSorted = field.name === 'n')){
            this._table.addClass('no-correct-n-filtered');
        }

        if (isNumber) {
            pcTable.dataSorted = pcTable.dataSorted.sort(function (a, b) {
                let a1 = data[a][fieldName].v;
                let b1 = data[b][fieldName].v;

                let _a1, _b1;

                try {
                    _a1 = Big(a1);
                } catch (e) {
                    _a1 = Big(0);
                }
                try {
                    _b1 = Big(b1);
                } catch (e) {
                    _b1 = Big(0);
                }
                let r = 0;

                if (_a1.gt(_b1)) r = sortDirection;
                else if (_a1.eq(_b1)) r = 0;
                else r = -sortDirection;

                return r;
            });
        }  else if (field.type === 'select') {
            pcTable.dataSorted = pcTable.dataSorted.sort(function (a, b) {
                let a_, b_;

                const getVal = function(a){
                    let a_;

                    if (data[a][fieldName].v_) {
                        if (field.multiple) {
                            a_='';
                            data[a][fieldName].v_.forEach(function (v_) {
                                a_+=v_[0];
                            })
                        }else {
                            a_ = data[a][fieldName].v_[0]
                        }
                    }else{
                        a_ = data[a][fieldName].v;

                    }
                    return a_;
                };

                a_ = getVal(a);
                b_ = getVal(b);

                if (a_ === b_) return 0;
                else if (a_ > b_) return sortDirection;
                else return -sortDirection;
            });
        }
        else {
            pcTable.dataSorted = pcTable.dataSorted.sort(function (a, b) {
                let a_, b_;
                a_ = data[a][fieldName].v || '';
                b_ = data[b][fieldName].v || '';
                if (a_ > b_) return sortDirection;
                else if (a_ == b_) return 0;
                else return -sortDirection;
            });
        }
        this.dataSortedVisible = [];
        pcTable.dataSorted.every(function (id) {
            if (pcTable.data[id].$visible) pcTable.dataSortedVisible.push(id);
            return true;
        });

        pcTable._refreshContentTable();
    }
});
App.pcTableMain.prototype.isSelected = function (fieldName, itemId) {
    if (this.selectedCells && this.selectedCells.ids[fieldName] && this.selectedCells.ids[fieldName].indexOf(itemId) !== -1) {
        return true;
    }
    return false;
};

App.pcTableMain.prototype._addSelectable = function () {
    var pcTable = this;
    pcTable.selectedCells = {
        fieldName: null,
        ids: {},
        notRowCell: null,
        selectPanel: null,
        lastSelected: null,
        notRowCellEmpty: function () {
            if (this.notRowCell) {
                this.notRowCell.removeClass('selected');
                let tr = this.notRowCell.closest('.DataRow');
                if (tr.length === 1) {
                    tr.removeClass('selected');
                }
                this.notRowCell = null;
            }
        },
        empty: function () {
            this.notRowCellEmpty();
            let selected = this;
            Object.keys(this.ids).forEach(function (fieldName) {
                selected.ids[fieldName].forEach(function (id) {
                    let item = pcTable._getItemById(id);
                    if (item && item.$tr) {
                        item.$tr.find('.selected').removeClass('selected');
                        item.$tr.removeClass('selected');
                    }
                });

            });
            this.ids = {};
            this.lastSelected = null;
        },
        getEditedData: function (val, fix) {
            let editedData = {};
            let isMulti = false;
            if (Object.keys(pcTable.selectedCells.ids).length > 1) {
                isMulti = true;
            }

            Object.keys(pcTable.selectedCells.ids).forEach(function (fieldName) {
                pcTable.selectedCells.ids[fieldName].forEach(function (id) {

                    let item = pcTable.data[id];
                    let isFieldBlocked = item[fieldName].f ? item[fieldName].f.block : undefined;

                    if ((item.f.block && isFieldBlocked !== false) || isFieldBlocked || !pcTable.fields[fieldName].editable || !pcTable.fields[fieldName].editGroup) return;
                    if (isMulti && !pcTable.fields[fieldName].editGroupMultiColumns) return;

                    if (!editedData[id]) editedData[id] = {};
                    if (fix) {
                        editedData[id][fieldName] = item[fieldName]['v'];
                    } else {
                        editedData[id][fieldName] = val;
                    }

                })
            });
            return editedData;
        },
        remove: function (id, fieldName) {
            let selected = this;
            if (!this.ids[fieldName]) return;

            let removedIds = {};

            this.ids[fieldName].some(function (iId, index) {
                if (iId == id) {
                    selected.ids[fieldName].splice(index, 1);
                    if (selected.ids[fieldName].length === 0) {
                        delete selected.ids[fieldName];

                    }
                    removedIds[id] = true;
                    return true;
                }
            });

            Object.keys(removedIds).forEach(function (rId) {
                let isSelected = false;
                Object.keys(selected.ids).some(function (fieldName) {
                    if (selected.ids[fieldName].indexOf(parseInt(rId)) !== -1) {
                        isSelected = true;
                        return true;
                    }
                });
                if (!isSelected) {
                    if (pcTable.data[rId].$tr) {
                        pcTable.data[rId].$tr.removeClass('selected');
                    }
                }
            });
        },
        add: function (id, fieldName) {
            if (!this.ids[fieldName]) {
                this.ids[fieldName] = [];
            }
            this.ids[fieldName].push(id);
            if (pcTable.data[id].$tr) {
                pcTable.data[id].$tr.addClass('selected');
            }
        },
        selectPanelDestroy: function () {
            let panelObj = this;

            if (panelObj.selectPanel) {
                if (panelObj.selectPanel.attr('aria-describedby')) {
                    panelObj.selectPanel.popover('destroy');
                }
                panelObj.selectPanel = null;
            }
            $('body').off('.selectPanelDestroy');
        },
        checkIfShowPanel: function (td) {
            "use strict";

            let selectObject = this;


            let eventNameKeyUp = 'keyup.selectPanelDestroy';
            let eventNameClick = 'click.selectPanelDestroy';

            this.selectPanelDestroy();


            if (td) {
                let field = pcTable._getFieldBytd(td);

                let $panel = $('<div id="selectPanel" class="text">');

                let textDivHeight = 200;

                let columnName = $('<div class="column-name"></div>').text(field.title);
                if (field.unitType) {
                    columnName.append(', ' + field.unitType);
                }
                $panel.append(columnName);

                if (field.type === 'text') {
                    columnName.append(', ' + field.textType);
                }
                $panel.append(columnName);

                let item;
                if (field.category === 'column') {
                    item = pcTable._getItemBytd(td);
                } else {
                    item = pcTable.data_params;
                }

                if (field.category === 'column') {
                    let rowName = '[' + item.id + ']';
                    if (pcTable.tableRow.main_field) {
                        let mainField = pcTable.mainFieldName;
                        if (item[mainField].v_ !== undefined) {
                            if (typeof item[mainField].v_ === 'array') {
                                item[mainField].v_.forEach(function (v_, i) {
                                    let d = $('<span>').text(pcTable.fields[mainField].getElementString((item[mainField].v ? item[mainField].v[i] : null), v_));
                                    if (v_[1]) {
                                        d.addClass('deleted_value')
                                    }
                                    rowName += ' ' + d.html();
                                })
                            } else {
                                rowName += ' ' + pcTable.fields[mainField].getElementString(item[mainField].v, item[mainField].v_);
                            }
                        } else {
                            rowName += ' ' + item[mainField].v;
                        }
                    }
                    let columnName = $('<div class="row-name"></div>').text(rowName);

                    $panel.append(columnName);
                }


                let val = item[field.name];


                let textDiv = $('<div class="field-value"><div class="copytext"></div></div>').css('white-space', 'pre-wrap').height(textDivHeight).appendTo($panel);
                if (field.unitType && (val.v) !== null) {
                    textDiv.attr('data-unit', field.unitType);
                }
                let textInner = textDiv.find('.copytext');

                if (val.f && val.f.text) {
                    $panel.append($('<div>').text(val.f.text));
                }

                let btnCopy;
                let btns = $('<div class="buttons"></div>');

                //edit
                if (td.is('.edt')) {
                    $('<button class="btn btn-sm btn-default"><i class="fa fa-pencil-square-o"></i></button>')
                        .on('click', function () {
                            td.dblclick();
                            return false;
                        })
                        .appendTo(btns);
                }
                //copy
                {
                    btnCopy = $('<button class="btn btn-sm btn-default copy_me" disabled data-copied-text="Скопировано" title="Копировать "><i class="fa fa-copy"></i></button>');
                    btnCopy.on('click', function () {
                        if (textInner.data('text')) {
                            App.copyMe(textInner.data('text'));
                        } else {
                            App.copyMe(textInner.text());
                        }
                        let button = $(this);
                        button.width(button.width());
                        button.button('copied');
                        setTimeout(function () {
                            button.button('reset');
                        }, 1000);
                        button.blur();
                        return false;
                    });

                    btns.append(btnCopy);
                }


                //log
                if (pcTable.tableRow.type !== 'tmp' && field.logButton) {
                    $('<button class="btn btn-sm btn-default" title="Лог ручных изменений по полю">Лог</button>')
                        .on('click', function () {
                            let rowName;
                            if (pcTable.mainFieldName && item.id) {
                                rowName = item[pcTable.mainFieldName].v;
                            }
                            pcTable.model.getFieldLog(field['name'], item.id, rowName);
                            let btn = $(this).addClass('disabled');
                            setTimeout(function () {
                                btn.removeClass('disabled');
                            }, 1000);
                            return false;
                        })
                        .appendTo(btns);
                }

                //filter

                if (field.category === 'column' && field.filterable) {
                    if (pcTable.isValInFilters.call(pcTable, field.name, val)) {
                        $('<button class="btn btn-sm btn-warning" title="Удалить из фильтра"><i class="fa fa-filter"></i></button>')
                            .on('click', function () {
                                selectObject.selectPanelDestroy();
                                pcTable.removeValueFromFilters.call(pcTable, field.name, val)
                                return false;
                            })
                            .appendTo(btns);
                    } else {
                        $('<button class="btn btn-sm btn-default" title="Добавить в фильтр"><i class="fa fa-filter"></i></button>')
                            .on('click', function () {
                                selectObject.selectPanelDestroy();
                                pcTable.addValueToFilters.call(pcTable, field.name, val);
                                pcTable._container.scrollTop(pcTable._filtersBlock.offset().top - pcTable.scrollWrapper.offset().top);
                                pcTable.ScrollClasterized.insertToDOM.call(pcTable.ScrollClasterized, 0);
                                return false;
                            })
                            .appendTo(btns);

                    }
                }

                //expand
                {
                    let btn = $('<button class="btn btn-sm btn-default"><i class="fa fa-expand" style="padding-top: 3px;" aria-hidden="true"></i></button>');
                    let width = false;
                    let position = {};
                    let height;
                    let parent;
                    btn.on('click', function () {
                        let obj = $panel.closest('.popover');
                        if (!width) {
                            width = true;
                            if (!position.top) {
                                position.top = obj.position().top;
                                position.left = obj.position().left;
                            }
                            parent=obj.parent();
                            /*obj.appendTo('body');*/
                            obj.addClass('super-wide');
                            height=obj.find('.field-value').height();
                            obj.find('.field-value').height("100%");


                            obj.css('left', ($('#table').width() - obj.width()) / 2);
                            obj.css('top', 20);


                            btn.find('i').removeClass('fa-expand').addClass('fa-compress')
                        } else {

                            obj.css('left', position.left);
                            obj.css('top', position.top);
                            btn.find('i').removeClass('fa-compress').addClass('fa-expand');
                            obj.removeClass('super-wide');
                            obj.find('.field-value').height(height);
                            obj.appendTo(parent);
                            width = false;
                        }
                    });
                    btns.append(btn);
                }

                //close
                $('<button class="btn btn-sm btn-default" title="Закрыть панель"><i class="fa fa-times"></i></button>')
                    .on('click', function () {
                        selectObject.selectPanelDestroy();
                        return false;
                    })
                    .appendTo(btns);


                const applyText = function (text) {

                    textInner.text(text);
                    btnCopy.prop('disabled', false);
                };
                const apply$ = function ($html, text) {
                    textInner.html($html);
                    if ($html.data('text')) {
                        textInner.data('text', $html.data('text'))
                    } else {
                        textInner.data('text', text);
                    }
                    btnCopy.prop('disabled', false);
                };

                let fieldText = field.getPanelText(val.v, $panel, item);
                //if (fieldText === null || fieldText === '') return false;


                if (pcTable.isCreatorView) {
                    if (['select', 'tree', 'date'].indexOf(field.type) !== -1) {
                        textDiv.after($('<div class="creator-select-val">' + JSON.stringify(val.v) + '</div>'));
                    }
                }

                const __applyText = function (fieldText) {
                    if (typeof fieldText === 'object' && fieldText !== null) {
                        if (fieldText instanceof jQuery) {
                            let text = '';
                            if (fieldText.copyText) {
                                apply$(fieldText, fieldText.copyText);
                            } else {
                                fieldText.each(function () {
                                    if (text !== '') text += "\n";
                                    text += $(this).text();
                                });
                                if (text === '') text = fieldText.text();
                                apply$(fieldText, text);
                            }
                        } else if (fieldText.then) {
                            fieldText.then(__applyText)
                        } else {
                            applyText(JSON.stringify(fieldText));
                        }
                    } else {
                        applyText(fieldText);
                    }
                };
                __applyText(fieldText);


                //log
                if (pcTable.isCreatorView) {

                    let itemLog;
                    if (pcTable.LOGS) {
                        itemLog = pcTable.LOGS;
                        if (item && item.id) {
                            itemLog = pcTable.LOGS[item.id];
                        }

                        if (itemLog) {
                            itemLog = itemLog[field.name];
                        }
                    }
                    let logs = $('<div style="padding-top: 10px">');


                    if (itemLog && itemLog.c) {
                        let log = $('<button class="btn btn-sm btn-danger"><i class="fa fa-info" style="padding-top: 3px;" aria-hidden="true"> c</i></button>');
                        log.on('click', function () {
                            App.logOutput(itemLog.c);
                        });
                        logs.append(log);
                    }


                    if (itemLog && itemLog.s) {
                        let log = $('<button class="btn btn-sm btn-danger"><i class="fa fa-info" style="padding-top: 3px;" aria-hidden="true"> s</i></button>');
                        log.on('click', function () {
                            App.logOutput(itemLog.s);
                        });
                        logs.append(log);
                    }
                    if (itemLog && itemLog.a) {
                        let log = $('<button class="btn btn-sm btn-danger"><i class="fa fa-info" style="padding-top: 3px;" aria-hidden="true"> a</i></button>');
                        log.on('click', function () {
                            App.logOutput(itemLog.a);
                        });
                        logs.append(log);
                    }
                    if (itemLog && itemLog.f) {
                        let log = $('<button class="btn btn-sm btn-danger"><i class="fa fa-info" style="padding-top: 3px;" aria-hidden="true"> f</i></button>');
                        log.on('click', function () {
                            App.logOutput(itemLog.f);
                        });
                        logs.append(log);
                    }
                    if (logs.children().length) {
                        btns.append(logs);
                    }
                }
                this.selectPanel = td.find('span:first');


                $panel.append(btns);

                if (val.e) {
                    $panel.append($('<div style="padding-top: 5px;">').html($('<span>').text(val.e).text().replace(/\[\[(.*?)\]\]/g, '<b>$1</b>')));
                }


                let placement = 'right';
                let spanOffsetLeft = this.selectPanel.offset().left,
                    containerOffsetLeft = pcTable._container.offset().left,
                    containerWidth = pcTable._container.width(),
                    tdWidth = this.selectPanel.width(),
                    panelWidth = $panel.is('.text') ? 340 : 240,
                    placeToRight = (containerWidth - (spanOffsetLeft - containerOffsetLeft) - tdWidth);


                if (placeToRight < panelWidth) {
                    placement = 'left';
                }

                let params = {
                    'isParams': true,
                    '$text': $panel,
                    'element': this.selectPanel,
                    'container': pcTable._container,
                    'placement': placement,
                    'trigger': 'manual'
                };
                App.popNotify(params);

                if (field.type === 'select') {
                    let _panel = $('<div class="previews">').appendTo($panel.find('.field-value'));
                    field.loadPreviewPanel(_panel, field.name, item, val['v']);
                }


                $('body').on(eventNameClick, function (event) {
                    if ($(event.target).closest('#selectPanel').length === 0) {
                        selectObject.selectPanelDestroy();
                    }
                })
                    .on(eventNameKeyUp, function (event) {
                        if (event.which == 27) {
                            selectObject.selectPanelDestroy();
                        }
                    });
            }

            return false;
        },
        copySepected: function (withNames, onDoneClbck) {
            let pcTable = this;
            let result = '';
            let allIds = [];
            let allFields = [];

            let data = {};
            let deffs = [];


            Object.keys(pcTable.selectedCells.ids).forEach(function (field) {
                let ids = pcTable.selectedCells.ids[field];
                allIds = allIds.concat(ids);
                allFields.push(field);

                ids.forEach(function (id) {
                    if (!data[id]) data[id] = {};

                    let res = pcTable.fields[field].getCopyText.call(pcTable.fields[field], pcTable.data[id][field], pcTable.data[id]);
                    if (typeof res === 'object') {
                        deffs.push(res);
                        res.done(function (resData) {

                            data[id][field] = resData;
                        })
                    } else {
                        data[id][field] = res;
                    }
                })
            });
            allIds = Array.from(new Set(allIds));
            allIds = pcTable.dataSortedVisible.filter(id => allIds.indexOf(id) !== -1);
            allFields = Array.from(new Set(allFields));
            let fields = [];
            pcTable.fieldCategories.visibleColumns.forEach(function (field) {
                if (allFields.indexOf(field.name) !== -1) {
                    fields.push(field.name)
                }
            });
            allFields = fields;
            const DELIM = "\t";

            if (withNames) {
                result += 'id';
                allFields.forEach(function (field) {
                    result += DELIM;

                    result += pcTable.fields[field].title;
                });
            }

            let onDoneClbck2 = onDoneClbck;

            $.when(...deffs).done(function () {
                allIds.forEach(function (id) {
                    if (result !== '') result += "\n";
                    let start = true;
                    if (withNames) {
                        result += id;
                        start = false;
                    }
                    allFields.forEach(function (field) {
                        if (start === true) start = false;
                        else {
                            result += DELIM;
                        }
                        let _str = data[id][field];

                        if (typeof _str === "undefined") _str = "";

                        if (typeof _str == 'string' && _str.replace(/\t/g, '').match(/[\s"]/)) {
                            _str = '"' + _str.replace(/"/g, '""') + '"';
                        }
                        result += _str;
                    });
                });
                console.log(result);
                App.copyMe(result);
                setTimeout(onDoneClbck2, 400);
            });


        },
        click: function (td, event) {
            let table = pcTable._table;

            if (td.closest('table').is('.pcTable-filtersTable')) return false;


            if (table.data('moved') === true) {
                table.data('moved', false);
                return false;
            }


            DoIt.call(this);

            function DoIt() {

                if (td.is('.val')/* || !td.is('.edt')*/) {
                    if (this.notRowCell && this.notRowCell.index(td) !== -1) {
                        pcTable.selectedCells.empty();
                    } else {
                        pcTable.selectedCells.empty();
                        this.notRowCell = td;
                        this.notRowCell.addClass('selected');
                        let tr = this.notRowCell.closest('.DataRow');
                        if (tr.length === 1) {
                            tr.addClass('selected');
                        }

                    }
                    $('table.pcTable-table').removeClass('selected-multi').removeClass('selected-column');

                    return;
                } else {
                    this.notRowCellEmpty();
                }


                let tr = td.closest('tr');
                let item = pcTable._getItemByTr(tr);
                let field = pcTable._fieldByTd(td, tr);

                let fieldName = field.name;

                /*
                   altKey
                 */
                if (event.altKey) {
                    if (td.is('.selected')) {
                        pcTable.selectedCells.remove(item.id, fieldName);
                        td.removeClass('selected');
                    } else {
                        pcTable.selectedCells.add(item.id, fieldName);

                        td.addClass('selected');
                        this.lastSelected = [fieldName, item.id];
                    }
                }
                /*
                 shiftKey
                 */
                else if (event.shiftKey && Object.keys(pcTable.selectedCells.ids).length) {

                    let selected = this;
                    let ids = [];
                    let step = 'before';

                    pcTable.dataSortedVisible.some(function (_id) {
                        if (step === 'before') {
                            if (_id === item.id || _id === selected.lastSelected[1]) {
                                step = 'doIt';
                                ids.push(_id);

                                if (item.id === selected.lastSelected[1]) return true;
                            }
                        } else if (step === 'doIt') {
                            ids.push(_id);

                            if (_id === item.id || _id === selected.lastSelected[1]) {
                                return true;//stop
                            }
                        }
                    });

                    step = 'before';

                    let selectIt = function (field) {
                        ids.forEach(function (_id) {
                            let table_item = pcTable.data[_id];
                            if (!pcTable.isSelected(field.name, _id)) {
                                selected.add(_id, field.name);
                                if (table_item.$tr)
                                    pcTable._getTdByFieldName(field.name, table_item.$tr).addClass('selected');
                            }
                        });
                    };

                    pcTable.fieldCategories.column.some(function (field) {
                        if (field.showMeWidth > 0) {
                            if (step === 'before') {
                                if (field.name === fieldName || field.name === selected.lastSelected[0]) {
                                    step = 'doIt';
                                    selectIt(field);

                                    if (fieldName === selected.lastSelected[0]) return true;
                                }
                            } else if (step === 'doIt') {

                                selectIt(field);

                                if (field.name === fieldName || field.name === selected.lastSelected[0]) {
                                    return true;//stop
                                }
                            }
                        }

                    });
                    this.lastSelected = [fieldName, item.id];
                }
                /*
                 simple click
                 */
                else {
                    let selected = pcTable.isSelected(field.name, item.id);

                    pcTable.selectedCells.empty();
                    if (!selected) {
                        pcTable.selectedCells.add(item.id, fieldName);
                        td.addClass('selected');
                        this.lastSelected = [fieldName, item.id];
                    }
                }
                let SelectedKeys = Object.keys(pcTable.selectedCells.ids);
                if (SelectedKeys.length > 1) {
                    $('table.pcTable-table').addClass('selected-multi');
                } else if (SelectedKeys.length === 1 && Object.values(pcTable.selectedCells.ids)[0].length > 1) {
                    $('table.pcTable-table').removeClass('selected-multi').addClass('selected-column');
                } else {
                    $('table.pcTable-table').removeClass('selected-multi').removeClass('selected-column');
                }

            }


        }

    };
    this._container.on('contextmenu', '.DataRow td:not(.editing,.n,.id), td.val:not(.editing)', function () {
        let element = $(this);

        if (pcTable.selectedCells.selectPanel && pcTable.selectedCells.selectPanel.closest('td')[0] == element[0]) {
            pcTable.selectedCells.selectPanelDestroy();
        } else {
            pcTable.selectedCells.selectPanelDestroy();
            pcTable.selectedCells.empty();
            pcTable.selectedCells.checkIfShowPanel(element);
            pcTable.selectedCells.click(element, {});
        }

        return false;
    });
    this._container.on('click', '.DataRow td:not(.editing,.id,.n), td.val:not(.editing)', function (event) {

        let element = $(this);
        if (element.is('.cell-button')) {
            let field = pcTable._getFieldBytd(element);
            pcTable._buttonClick.call(pcTable, element, field);
            return false;
        }

        if (element.data('clicked')) {
            element.removeData('clicked');
        } else {
            element.data('clicked', 1);
            setTimeout(function () {
                if (element.data('clicked')) {
                    element.removeData('clicked');
                    pcTable.selectedCells.click(element, event);
                }
            }, 200);
        }
    });

    this._container.on('click', 'th.id .for-selected button', function () {
        let btn = $(this);
        let html = btn.html();
        btn.text('Скопировано');
        pcTable.selectedCells.copySepected.call(pcTable, btn.data('names'), function () {
            btn.html(html)
        });
    });

};
App.pcTableMain.prototype.filters = {};


App.pcTableMain.prototype.__getFilterButton = function (fieldName) {
    var colorClass = 'btn-default';
    var isFilters = (this.filters[fieldName] && this.filters[fieldName].length) || this.filters[fieldName + "/h"];
    if (isFilters) colorClass = 'btn-warning';
    var btn = $('<button class="btn btn-xxs btn-filter"><span class="fa fa-filter"></span></button>').addClass(colorClass);

    let pcTable = this;

    return $('<span class="filter-in-head">').append(btn);
};
App.pcTableMain.prototype.filtersEmpty = function () {
    this.filters = {};
    this._refreshHead();
    this.__applyFilters();
};
App.pcTableMain.prototype.sessionStorageFilters = {
    url: location.protocol + '//' + location.host + location.pathname,
    setFilters: function (filters) {
        var sessionFilters = {}
        var filters = filters || {};
        try {
            sessionFilters = JSON.parse(sessionStorage.getItem('pcTableFilters')) || {};
        } catch (error) {

        }
        sessionFilters[this.url] = filters;
        sessionStorage.setItem('pcTableFilters', JSON.stringify(sessionFilters));
    },
    getFilters: function () {
        var sessionFilters = {}

        try {
            sessionFilters = JSON.parse(sessionStorage.getItem('pcTableFilters'));
            sessionFilters = sessionFilters[this.url] || {};
        } catch (error) {

        }
        return sessionFilters;
    }
};
let isFirstStart = true;
App.pcTableMain.prototype.__applyFilters = function () {
    let pcTable = this;
    App.fullScreenProcesses.show();

    if (this.filtersClearButton) {
        if (pcTable.tableRow['type'] !== "tmp") {
            this.sessionStorageFilters.setFilters(this.filters);
        }
        this.selectedCells.empty();

        if (Object.equals(this.filters, {})) {
            this.filtersClearButton.addClass('btn-default').removeClass('btn-warning').attr('disabled', true)
        } else {
            this.filtersClearButton.removeClass('btn-default').addClass('btn-warning').removeAttr('disabled');
            if (isFirstStart) {
                App.blink(pcTable.filtersClearButton, 8, '#fff');
                isFirstStart = false;
            }
        }
    }


    let old = this.dataSortedVisible;
    //console.log(pcTable.filters);

    this.dataSortedVisible = [];
    for (let i = 0; i < this.dataSorted.length; i++) {
        let id = this.dataSorted[i];
        let item = this.data[id];
        this.__applyFiltersToItem(item);
        if (item.$visible) {
            this.dataSortedVisible.push(id);
        }
    }
    //console.log(this.dataSorted, this.dataSortedVisible);

    this._headCellIdButtonsState();
    if (JSON.stringify(old) !== JSON.stringify(this.dataSortedVisible)) {
        this._refreshContentTable(false, true);
    }
    App.fullScreenProcesses.hide();
};
App.pcTableMain.prototype.__applyFiltersToItem = function (item, notAttachIt) {
    let pcTable = this;
    let visible = true;

    for (let fieldName in pcTable.filters) {
        //if (!pcTable.filters[fieldName] || pcTable.filters[fieldName].length === 0) continue;

        let filterVals = pcTable.filters[fieldName];
        if (fieldName === 'id') {
            if (filterVals.indexOf(item['id'].toString()) === -1) {
                visible = false;
            }
        } else {
            let lst = fieldName.toString().split("/");
            fieldName = lst[0];
            let type = lst[1] || "v";
            switch (type) {
                case "v":
                    let field = pcTable._getFieldbyName(fieldName);
                    if (!field.checkIsFiltered(item[fieldName], filterVals)) {
                        visible = false;
                    }
                    break;
                case "h":
                    switch (filterVals) {
                        case 'h':
                            if (item[fieldName].h !== true) {
                                visible = false;
                            }
                            break;
                        case 'n':
                            if (item[fieldName].h === true) {
                                visible = false;

                            }
                            break;
                        case 'hf':
                            if (!(item[fieldName].h === true
                                && !item[fieldName].hasOwnProperty("c"))) {
                                visible = false;
                            }
                            break;
                        case 'hc':
                            if (!(item[fieldName].h === true
                                && item[fieldName].hasOwnProperty("c"))) {
                                visible = false;
                            }
                            break;

                    }
            }
        }
        if (!visible) break;
    }
    if (!(item.$visible = visible)) {
        this.row_actions_uncheck(item);
    }
};
App.pcTableMain.prototype.addValueToFilters = function (fieldName, valObj) {
    var pcTable = this;
    if (!pcTable.filters[fieldName]) pcTable.filters[fieldName] = [];
    let field = pcTable.fields[fieldName];
    pcTable.filters[fieldName].push(field.getFilterDataByValue.call(field, valObj));
    field.$th.find('.btn-filter').parent().replaceWith(pcTable.__getFilterButton.call(pcTable, fieldName));
    pcTable.__applyFilters.call(pcTable);
};
App.pcTableMain.prototype.isValInFilters = function (fieldName, valObj) {
    var pcTable = this;
    if (!pcTable.filters[fieldName]) pcTable.filters[fieldName] = [];
    let field = pcTable.fields[fieldName];
    let val = field.getFilterDataByValue.call(field, valObj);
    return pcTable.filters[fieldName].indexOf(val) !== -1;
};
App.pcTableMain.prototype.removeValueFromFilters = function (fieldName, valObj) {

    var pcTable = this;
    if (!pcTable.filters[fieldName]) pcTable.filters[fieldName] = [];
    let field = pcTable.fields[fieldName];

    let val = field.getFilterDataByValue.call(field, valObj), ax;
    while ((ax = pcTable.filters[fieldName].indexOf(val)) !== -1) {
        pcTable.filters[fieldName].splice(ax, 1);
    }
    if (pcTable.filters[fieldName].length === 0) {
        delete pcTable.filters[fieldName];
    }


    field.$th.find('.btn-filter').parent().replaceWith(pcTable.__getFilterButton.call(pcTable, fieldName));
    pcTable.__applyFilters.call(pcTable);

};
App.pcTableMain.prototype.__addFilterable = function () {
    var pcTable = this;

    var filters = {};
    if (pcTable.tableRow['type'] != "tmp")
        filters = pcTable.sessionStorageFilters.getFilters();
    if (filters)
        pcTable.filters = filters;
    else pcTable.filters = {};

    this._header.on('click', '.pcTable-filters > span button.btn-filter:not(#checkS)', function (event) {

        let btn = $(this);
        if (btn.attr('aria-describedby')) return true;

        let th = btn.closest('th');
        let fieldName = th.is('.id') ? 'id' : th.data('field');

        let selectDiv = $('<div class="filter-div-button">');
        let select = $('<select class="selectpicker" data-size="6" multiple title="Выберите значения" data-style="btn-sm btn-default" data-width="css-width" data-live-search="true" data-selected-text-format="count">').appendTo(selectDiv);

        const popoverDestroy = function () {
            try {
                btn.popover('destroy');
            } catch (e) {

            }
        };

        const setSelectedFilters = function () {
            popoverDestroy();
            let j1 = JSON.stringify(pcTable.filters[fieldName] || []);
            let j2 = JSON.stringify(select.selectpicker('val'));
            if (j1 !== j2) {
                pcTable.filters[fieldName] = select.selectpicker('val');
                if (pcTable.filters[fieldName].length === 0) delete pcTable.filters[fieldName];
                if (fieldName === 'id' && !btn.closest('.pcTable-table').is(".pcTable-table:first")) {
                    pcTable._header.find('th.id .btn-filter').parent().replaceWith(pcTable.__getFilterButton.call(pcTable, fieldName));
                }
                btn.parent().replaceWith(pcTable.__getFilterButton.call(pcTable, fieldName));
                pcTable.__applyFilters.call(pcTable);
            }

        };
        const filterRemove = function () {
            popoverDestroy();

            delete pcTable.filters[fieldName];
            btn.removeClass('btn-warning').addClass('btn-default');
            pcTable.__applyFilters.call(pcTable);
        };

        let isDeleteAction = false;
        let objTimeout;
        const actionIt = function (actionName) {
            if (objTimeout) clearTimeout(objTimeout);

            if (isDeleteAction) return;
            if (actionName === 'filterRemove') {
                isDeleteAction = true;
                filterRemove();
                return;
            }
            if (actionName === 'setInvertFilters') {
                let selected = Object.values(select.selectpicker('val'));
                let newSelected = [];
                $.each(select.data('options'), function (k, v) {
                    if (selected.indexOf(v) === -1) {
                        newSelected.push(v);
                    }
                });
                select.selectpicker('val', newSelected);
            }
            objTimeout = setTimeout(function () {
                setSelectedFilters();
            }, 10);
        };


        selectDiv = $('<div class="pcTable-filter-select" style="height: 220px;">').append(selectDiv);
        select.data('container', selectDiv);

        var vals = {};
        $.each(pcTable.data, function (_id, v) {
            if (fieldName === 'id') {
                vals[_id.toString()] = _id.toString();
            } else {
                pcTable.fields[fieldName].addDataToFilter(vals, v[fieldName]);
            }
        });


        var filterOptions = {};

        $.each(vals, function (k, v) {
            filterOptions[v] = k;
        });
        filterOptions = App.ksort(filterOptions);

        let optgroups = {'Выбранное': $('<optgroup label="Выбранное">'), '': $('<optgroup label="">')};

        $.each(filterOptions, function (k, v) {
            if (pcTable.filters[fieldName] !== undefined && pcTable.filters[fieldName].indexOf(v.toString()) !== -1) {
                optgroups['Выбранное'].append('<option data-content="' + k + '">' + v + '</option>');
            } else {
                optgroups[''].append('<option data-content="' + k + ' ">' + v + '</option>');
            }
        });
        select.append(optgroups['Выбранное']);
        select.append(optgroups['']);
        select.data('options', filterOptions);

        var popover = btn.popover({
            html: true,
            content: selectDiv,
            trigger: 'manual',
            container: pcTable._container,
            placement: 'auto bottom',
            template: '<div class="popover" role="tooltip" style=""><div class="arrow" style="left: 50%;"></div><div class="popover-content" style=" padding: 3px 5px;"></div></div>'
        });
        select.selectpicker('render').selectpicker('toggle');

        let $buttons = $('<div class="buttons" style="position: absolute; bottom: -4px; width: 100%; text-align: center">');

        $('<span class="btn btn-default btn-xxs button-ok" style="margin-right: 4px; margin-top: 3px;">Прим.</span></span>').appendTo($buttons).on('click', function () {
            actionIt('setSelectedFilters');
        });
        $('<span class="btn btn-default btn-xxs button-ok" style="margin-right: 4px; margin-top: 3px;">Инверт.</span></span>').appendTo($buttons).on('click', function () {
            actionIt('setInvertFilters');
        });
        $('<span class="btn btn-default btn-xxs button-ok" style="margin-right: 4px; margin-top: 3px">Отмен.</span>').appendTo($buttons).on('click', function () {
            actionIt('filterRemove');
        });

        if (pcTable.fields[fieldName] && pcTable.fields[fieldName].code && !pcTable.fields[fieldName].codeOnlyInAdd) {
            let h_select = $('<select data-title="Все" data-style="btn btn-xxs ' + (pcTable.filters[fieldName + "/h"] ? 'btn-warning' : 'btn-default') + ' ">' +
                '<option value="">Все</option>' +
                '<option value="n">Без руки</option>' +
                '<option value="h">С рукой все</option>' +
                '<option value="hf">С рукой как в расчете</option>' + //<i class="fa fa-hand-rock-o pull-right"></i>
                '<option value="hc">С рукой отличающиеся</option>' + //<i class="fa fa-hand-paper-o pull-right"></i>
                '</select>').appendTo($buttons).on('change', function () {
                pcTable.filters[fieldName + "/h"] = h_select.selectpicker('val');
                if (pcTable.filters[fieldName + "/h"] === "") {
                    delete pcTable.filters[fieldName + "/h"];
                }
                popoverDestroy();
                btn.parent().replaceWith(pcTable.__getFilterButton.call(pcTable, fieldName));
                pcTable.__applyFilters.call(pcTable);
                return false;
            }).wrap('<span id="filterHand">');

            setTimeout(function () {
                h_select.selectpicker('render').selectpicker("val", pcTable.filters[fieldName + "/h"] || "");
            }, 100)
        }


        $buttons.appendTo(selectDiv);
        select.on('hidden.bs.select', function () {
            actionIt('setSelectedFilters');
        });

        if (pcTable.filters[fieldName]) select.selectpicker('val', pcTable.filters[fieldName]);

        setTimeout(function () {

            if (popover && popover.length) {
                popover.popover('show');
                selectDiv.on('mouseenter', 'li', function () {
                    let self = $(this);
                    if (!self.attr('title')) {
                        self.attr('title', self.text());
                    }
                });
                if (fieldName === 'id') {
                    $('#' + btn.attr('aria-describedby')).position({
                        my: "left top",
                        at: "left-3px bottom+10px",
                        of: btn
                    }).find('.arrow').css('left', '12px')
                }
                select.data('selectpicker')._searchStyle = function () {
                    return 'multiincludes';
                };


                select.data('selectpicker').$searchbox.focus();

                pcTable._container.one('click.filter filterPressed.filter', function (e) {
                    if ($(e.target).closest("#filterHand").length === 0 && (e.type === "filterPressed" || e.altKey !== undefined) && $('#' + btn.attr('aria-describedby')).is(':visible')) { //Это чтобы не отлавливать всякие технические события
                        pcTable._container.off('.filter');
                        actionIt('setSelectedFilters');
                    }
                });
                pcTable._innerContainer.one('scroll.filter', function (e) {
                    if ($('#' + btn.attr('aria-describedby')).is(':visible')) {
                        pcTable._container.off('.filter');
                        actionIt('setSelectedFilters');
                    }
                });
            }
        }, 50);
        pcTable._container.trigger('filterPressed');
    });

};


$.extend(App.pcTableMain.prototype, {
    _addEditable: function () {
        var pcTable = this;

        let arias = this._container;

        arias.on('dblclick', 'td.val:not(.editing), td.edt:not(.editing), .dataRows td:not(.editing,.id,.n)', function (event) {
            let cell = $(this);
            let tr = cell.closest('tr');
            if (tr.length && tr.is('.InsertRow')) return false; // || tr.closest('.pcTable-filtersTable').length === 1

            if (cell.is('.footer-name, .id, .footer-empty')) return false;

            if (cell.is('.edt')) {
                pcTable._createEditCell.call(pcTable, cell, true)
            } else {
                let html = cell.html();
                cell.html('Заблокировано');
                setTimeout(function () {
                    cell.html(html);
                }, 500);
            }

        }).on('click', '.editCellsBlock .btn', function (event) {
            $(this).data('click')(event);
        });

    },
    goToEditNextCell: function (goTo) {

        return next;
    },
    _buttonClick: function ($td, field, item) {
        if ($td.data('clicked')) return false;


        const func = function () {
            "use strict";

            let id;
            let editedData = {};
            let tr = $td.parent();

            $td.data('clicked', true);

            if (field.category === 'column') {
                item = item || pcTable._getItemBytd($td);
                id = item.id;
                editedData.item = id;
                editedData.fieldName = field.name;
            }
            else {
                editedData.item = 'params';
                editedData.fieldName = field.name;
            }

            editedData.checked_ids = pcTable.row_actions_get_checkedIds();

            $td.find('button').hide();
            let $spinner = $('<div class="text-center"><i class="fa fa-spinner"/></div>');
            $td.append($spinner);

            pcTable._saving = true;
            pcTable.model.click(editedData)
                .then(
                    function (json) {
                        pcTable.table_modify.call(pcTable, json);
                        if ($td.length && $td.isAttached()) {
                            $spinner.remove();
                            $td.find('button').show();

                        } else {
                            if (field.category === 'column') {
                                let tr = pcTable._getItemById(id).$tr;
                                $td = pcTable._getTdByFieldName(field.name, tr);
                            } else {
                                $td = tr.find('[data-field="' + field.name + '"]');
                            }
                        }
                        if (field.uncheckAfterClick) {
                            pcTable.row_actions_uncheck_all();
                        }
                        if (field.closeIframeAfterClick && window.closeMe) {
                            window.closeMe();
                        }
                        field.btnOK.call(field, $td);
                    }
                ).fail(function () {
                if ($td.length && $td.isAttached()) {
                    $spinner.remove();
                    $td.find('button').show();
                    $td.removeData('clicked');

                }
            }).always(function () {
                pcTable._saving = false;
            });
        };

        let pcTable = this;
        if (field.warningEditText) {
            let buttons =
                {
                    'Ок': function (panel) {
                        panel.close();
                        func();
                    }, 'Отмена': function (panel) {
                        panel.close();
                    }
                };

            let dialog = App.confirmation(field.warningEditText, buttons, 'Подтверждение');

        } else {
            func()
        }

    },
    _saveEdited: function ($editObj, editedData, goTo) {
        let pcTable = this;
        if (pcTable._saving === true) return;
        pcTable._saving = true;

        this.model.save(editedData)
            .then(
                function (json) {

                    pcTable.table_modify.call(pcTable, json, undefined, $editObj);
                    if ($editObj.closest('table').length) {
                        if ($editObj.is('tr.DataRow')) {
                            pcTable.refreshRow($editObj);
                        }
                    }

                    if (goTo) {
                        goTo();
                    }
                }
            ).always(function () {

            pcTable._saving = false;

            if ($editObj.is('tr.DataRow') && $editObj.closest('table').length === 1 && $editObj.find('.editing').length) $editObj.each(function () {
                pcTable.refreshRow($(this))
            });
            else if ($editObj.is('td') && $editObj.find('.fa-spinner').length && $editObj.closest('table').length > 0) {
                $editObj.each(function () {
                    let $editObj = $(this);
                    let item = pcTable._getItemBytd($editObj);
                    let field = pcTable._getFieldBytd($editObj);
                    let $cell = pcTable._createCell(item, field);
                    if (field.type === 'button') {
                        field.btnOK.call(field, $cell);
                    }
                    $editObj.replaceWith($cell);
                })

            }


        });
    },
    _editFocusIndex: 0,
    _editItem: null,
    _editPanel: null,
    _row_edit: function (checkedList) {
        let pcTable = this;

        if (checkedList.length === 0) {
            return false;
        }
        let ItemId = checkedList.shift();

        (new EditPanel([pcTable.tableRow.id,pcTable.model.tableData.sess_hash, pcTable.data], null, {id:ItemId}, (checkedList.length > 0))).then(function (json, isNextPressed) {
            if (json || isNextPressed) {
                if(json){
                    pcTable.table_modify.call(pcTable, json);
                }
                pcTable._row_edit.call(pcTable, checkedList);
            }
        });
    },
    _currentEditCellIndex: 0,
    _removeEditing: function (td) {
        if (!td) td = this._content.find('td.editing');
        var td2 = this._createCell(this._getItemBytd(td), this._getFieldBytd(td));
        let checkVar;
        if (checkVar = td.attr('rowspan')) {
            td2.attr('rowspan', checkVar)
        }
        if (checkVar = td.data('field')) {
            td2.data('field', checkVar)
        }
        if (td.is('.val')) {
            td2.addClass('val')
        }
        td.replaceWith(td2);
        return td2;
    },
    _saveEditCell: function () {
        if (this._editCell && this._editCell.isAttached()) {
            if (this._editCell.is('.editCell') && this._editCell.data('SaveMe')) {
                this._editCell.data('SaveMe')();
            }
        }
    },
    _removeEditCell: function () {
        if (this._editCell && this._editCell.isAttached()) {
            if (this._editCell.is('.editCell')) {
                this._removeEditing(this._editCell);
            }
        }
        this._editCell = null;
    },
    _setEditCell: function (td) {
        this._saveEditCell();
        this._editCell = td;
        td.addClass('editCell');
    },
    _setTdSaving: function ($td) {
        $td.html('<div class="text-center"><i class="fa fa-spinner"/></div>');
    },
    _createEditCell: function (td, editNow, item) {
        let pcTable = this;

        let field = this._getFieldBytd(td);

        this._setEditCell(td);
        td.height(td.height());

        let tr = td.closest('tr');
        let columnIndex = pcTable._getColumnIndexByTd(td, tr);
        let goToFunc = function (direction) {
            if (!direction) return false;

            let next;
            switch (direction) {
                case 'right':
                    next = pcTable._getTdByColumnIndex.call(pcTable, tr, ++columnIndex);
                    while (next.length) {
                        let field = pcTable._getFieldBytd.call(pcTable, next);
                        if (field.editable === true) {
                            next.trigger('dblclick');
                            break;
                        }
                        else {
                            next = next.next('td');
                        }
                    }
                    break;
                case 'down':
                    next = tr.next('tr');
                    while (next.length && next.is('.Blocked')) {
                        next = next.next('tr');
                    }
                    pcTable._getTdByColumnIndex.call(pcTable, next, columnIndex).trigger('dblclick');
            }
        };

        if (!field.editable) return false;

        item = item || this._getItemBytd(td);
        let itemId = item.id;
        let editCellsBlock = $('<div class="editCellsBlock">');

        td.addClass('editing');

        let oldval = item[field.name];

        let onAction = false;

        let escClbck = function ($input, eventIN, tdIN) {

            let td = tdIN || $input.closest('td');
            let event = eventIN || {};

            if (!td.length || !td.closest('tr').length) return false;
            var tdNew = pcTable._removeEditing.call(pcTable, td);
            pcTable._colorizeElement(tdNew, pcTable_COLORS.blured);
            goToFunc(event.altKey ? 'right' : (event.shiftKey ? 'down' : false))
        };
        let revert = function (goTo) {
            pcTable._removeEditing.call(pcTable, td);
            goToFunc(goTo)
        };

        let isGroupSelected, isMultiGroupSelected;

        if (pcTable.isSelected(field.name, item.id)) {
            if (Object.keys(pcTable.selectedCells.ids).length > 1) {
                isMultiGroupSelected = true;
                isGroupSelected = true;
            }else if(pcTable.selectedCells.ids[field.name].length > 1){
                isGroupSelected = true;
            }
        }
        else {
            pcTable.selectedCells.empty();
        }


        let save = function (editVal, event, confirmed) {

            if (!confirmed && (field.warningEditPanel) && field.checkEditRegExp.call(field, editVal)) {
                App.confirmation((field.warningEditText || 'Точно изменить?'), {
                    'ОК': function (dialog) {
                        save(editVal, event, true);
                        dialog.close();
                    },
                    'Отменить': function (dialog) {
                        revert();
                        dialog.close();
                    }
                }, 'Предупреждение при изменении');
                return;
            }

            td.html('<div class="text-center"><i class="fa fa-spinner"/></div>');
            let editedData = {};
            editedData[field.name] = editVal;

            let EdData = {};
            if (!item.id) {
                EdData['params'] = editedData;
            }
            else {
                EdData[item.id] = editedData;
            }

            if (false && field.category === 'filter' ) {
                let filterData = {};
                $.each(pcTable.fieldCategories.filter, function (k, v) {
                    filterData[v.name] = pcTable.data_params[v.name].v;
                });
                filterData[field.name] = editVal;

                pcTable.model.calculateFilters(filterData, field.name).then(function (json) {
                    pcTable._refreshFiltersBlock.call(pcTable, json)
                });
            } else {
                pcTable._saveEdited.call(pcTable, td, EdData, function () {
                    goToFunc(event.altKey ? 'right' : (event.shiftKey ? 'down' : false))
                });
            }
        };


        let blurClbck = function ($input, event) {
            setTimeout(function () {
                if (onAction) {
                    onAction = false;
                    return false;
                }
                saveClbck($input, event);

            }, 150)
        };

        let saveClbck = function ($input, event, isFromButton) {

            onAction = true;

            isFromButton = isFromButton || false;
            if (!isFromButton && isGroupSelected) return false;

            let td = $input.closest('td');
            let editVal;
            try {
                editVal = field.getEditVal(input);
            }
            catch (error) {
                let notify = $('#' + App.popNotify(error, td, 'default'));
                notify.css('z-index', 1000);
                onAction = false;
                return;
            }

            let item = pcTable._getItemBytd(td);

            let goTo = event.altKey ? 'right' : (event.shiftKey ? 'down' : false);

            if (!field.isDataModified(editVal, item[field.name].v)) {
                revert(goTo);
            }
            else {
                save(editVal, event);

            }

        };


        var input = field.getEditElement(undefined, oldval, item, saveClbck, escClbck, blurClbck, null, editNow);
        td.html(input);
        td.data('SaveMe', function (event) {
            event = event || {};
            saveClbck(input, event);
        });

        td.data('input', input);

        var cdiv = $('<div class="cdiv">').css({height: 0, width: '100%', 'position': 'absolute', bottom: '0px'});
        td.append(cdiv);


//Сохранить
        var $btn = $('<button class="btn btn-sm btn-default" data-save="true" data-name="Сохранить"><i class="fa fa-save"/></button>')
            .data('click', function (event) {
                onAction = true;
                saveClbck(input, event, true);
            });

        editCellsBlock.append($btn)
        //Отменить
        var $btn = $('<button class="btn btn-sm btn-default" data-name="Закрыть"><i class="fa fa-undo"/></button>')
            .data('click', function (event) {
                onAction = true;
                let goTo = event.altKey ? 'right' : (event.shiftKey ? 'down' : false);
                revert(goTo)
            });

        editCellsBlock.append($btn);

        if (isGroupSelected && (isMultiGroupSelected?field.editGroupMultiColumns:field.editGroup)) {


            $btn = $('<button class="btn btn-sm btn-warning" data-save="true" data-name="Применить к выделенным"><i class="fa fa-database" title="Применить к выделенным"/></button>');

            let comboSave = function () {

                onAction = true;
                let editVal;
                try {
                    editVal = field.getEditVal(input);
                }
                catch (error) {
                    App.popNotify(error, td, 'default');
                    return;
                }
                let selectedTd = pcTable._container.find('td.selected');
                pcTable._setTdSaving(selectedTd);
                let editedData = pcTable.selectedCells.getEditedData(editVal);
                pcTable._saveEdited.call(pcTable, selectedTd, editedData, false);
            };


            $btn.data('click', function () {

                if (field.warningEditPanel) {
                    App.confirmation(field.warningEditText, {
                        'ОК': function (dialog) {
                            comboSave();
                            dialog.close();
                        },
                        'Отменить': function (dialog) {
                            revert();
                            dialog.close();
                        }
                    }, 'Предупреждение при изменении');
                    return;
                }
                comboSave();

            });
            editCellsBlock.append($btn);

            if (field.code && !field.codeOnlyInAdd) {

                $btn = $('<button class="btn btn-sm btn-warning" data-name="Фиксировать выделенные"><i class="fa fa-hand-rock-o" title="Фиксировать"/></button>');
                $btn.data('click', function () {
                    onAction = true;
                    let selectedTd = pcTable._container.find('td.selected');
                    pcTable._setTdSaving(selectedTd);
                    let editedData = pcTable.selectedCells.getEditedData(null, true);
                    pcTable._saveEdited.call(pcTable, selectedTd.closest('tr'), editedData, false);
                });
                editCellsBlock.append($btn);

                $btn = $('<button class="btn btn-sm btn-danger" data-name="Сбросить ручные"><i class="fa fa-eraser" title="Сбросить ручные"/></button>');
                $btn.data('click', function () {
                    onAction = true;
                    let selectedTd = pcTable._container.find('td.selected');
                    pcTable._setTdSaving(selectedTd);
                    let editedData = pcTable.selectedCells.getEditedData('NULL');
                    editedData['setValuesToDefaults'] = true;
                    pcTable._saveEdited.call(pcTable, selectedTd.closest('tr'), editedData, false);
                });
                editCellsBlock.append($btn)

            }

        }
        else if (item[field.name] && item[field.name].h == true) {
            $btn = $('<button class="btn btn-sm btn-danger" data-name="Сбросить ручное"><i class="fa fa-eraser" title="Сбросить ручное"/></button>');
            $btn.data('click', function () {
                onAction = true;
                td.html('<div class="text-center"><i class="fa fa-spinner"/></div>');
                let editedData = {};
                if (!parseInt(itemId)) itemId = 'params';
                editedData[itemId] = {};
                editedData[itemId][field.name] = 'NULL';

                editedData['setValuesToDefaults'] = true;
                pcTable._saveEdited.call(pcTable, td, editedData, false);
            });
            editCellsBlock.append($btn)
        } else if (field.code && !field.codeOnlyInAdd) {
            $btn = $('<button class="btn btn-sm btn-default" data-name="Фиксировать"><i class="fa fa-hand-rock-o" title="Фиксировать"/></button>');
            $btn.data('click', function () {
                onAction = true;
                td.html('<div class="text-center"><i class="fa fa-spinner"/></div>');
                let editedData = {};
                if (!parseInt(itemId)) itemId = 'params';
                editedData[itemId] = {};
                editedData[itemId][field.name] = itemId === 'params' ? pcTable.data_params[field.name]['v'] : pcTable.data[itemId][field.name]['v'];

                pcTable._saveEdited.call(pcTable, td, editedData, false);
            });
            editCellsBlock.append($btn)
        }

        if (field.changeSelectTable) {

            let sourseBtnClick = function () {
                onAction = true;
                let ee = {};
                $.each(item, function (k, v) {
                    if (k.substring(0, 1) !== '$') {
                        ee[k] = v;
                    }
                });
                let isAdd = $(this).data('add-button');
                if (isAdd) {
                    ee[field.name] = null;
                }
                let opened = 0;
                $(window.top.document.body)
                    .on('pctable-opened.select-' + field.name, function () {
                        opened++;
                    })
                    .on('pctable-closed.select-' + field.name, function (event, data) {
                        opened--;
                        let isAdded = (data /*&& data.tableId === field.selectTableId*/ && data.method === 'insert' && data.json && data.json.chdata && data.json.chdata.rows);
                        const refreshInputAndPage = function () {
                            if (opened === 0 || isAdded) {
                                let inputOld = input;
                                delete field.list;
                                if (inputOld.data('input').data('LISTs')) {
                                    inputOld.data('input').data('LISTs').isListForLoad = true;
                                }
                                item = $.extend(true, {}, item);
                                if (isAdded) {

                                    if (field.multiple) {
                                        item[field.name].v.push(Object.keys(data.json.chdata.rows)[0]);
                                    } else {
                                        item[field.name].v = Object.keys(data.json.chdata.rows)[0];
                                    }

                                }

                                if (!isAdded && field.category === 'column') {
                                    pcTable.model.refresh(function (json) {
                                        pcTable.table_modify.call(pcTable, json);
                                    });
                                }
                                item[field.name].replaceViewValue=function(viewArray){
                                    if(field.category!='column'){
                                        pcTable.data_params[field.name].v_=viewArray;
                                    }
                                };
                                inputOld.replaceWith(input = field.getEditElement(inputOld, item[field.name], item, saveClbck, escClbck, blurClbck));

                                $('body').off('.select-' + field.name);
                            }
                        };
                        setTimeout(refreshInputAndPage, 100);//Чтобы успело открыться окошко слещующей панели, если оно есть
                    });
                pcTable.model.selectSourceTableAction(field.name, ee);
                return false;
            };


            $btn = $('<button class="btn btn-sm btn-primary"><i class="fa fa-edit" title="Изменить в таблице-источнике"/></button>');
            $btn.on('click', sourseBtnClick);
            editCellsBlock.append($btn);
            if (field.changeSelectTable === 2) {
                $btn = $('<button class="btn btn-sm btn-primary" data-add-button="true"><i class="fa fa-plus" title="Добавить в таблицу-источник"/></button>');
                editCellsBlock.append($btn);
                $btn.on('click', sourseBtnClick);
            }
        }
        let btnCount = editCellsBlock.find('button').length;
        editCellsBlock.width(btnCount * 31);




        //  let element = $('#' + App.popNotify(editCellsBlock, cdiv, null, this._container));
        let element = $('#' + App.popNotify({
            $text: editCellsBlock,
            element: cdiv,
            container: this._container,
            isParams: true,
            placement: 'bottom'
        }));

        let top = parseInt(element.css('top')) - 4;
        element.css('top', -10000000);
        setTimeout(function () {
            if (element.length && element.isAttached()) {
                element.css('top', top);
            }
        }, 3);
        field.focusElement(input);
    }
});
(function (window, $) {
    $.extend(App.pcTableMain.prototype, {
        _orderSaveBtn: undefined,
        row_actions_add: function () {
            let pcTable = this;

            pcTable._table.on('mouseenter', '.DataRow .id', function () {
                pcTable.row_actions_icons_show.call(pcTable, $(this))
            });
            pcTable._table.on('mouseout', '.DataRow .id', function (event) {
                let $tdId = $(this);
                if ($(event.target).is('.id')) {
                    if (!$tdId.is(':hover')) {
                        pcTable.row_actions_icons_hide.call(pcTable, $tdId.closest('tr'))
                    } else {
                        setTimeout(function () {
                            if (!$tdId.is(':hover')) {
                                pcTable.row_actions_icons_hide.call(pcTable, $tdId.closest('tr'))
                            }
                        }, 100)
                    }
                }
                return false;
            });
            pcTable._table.on('click', '.DataRow .id button.dropdown', function () {
                pcTable.row_dropdown.call(pcTable, $(this))
            });
            pcTable._table.on('click', '.DataRow .id .btn.chbox', function (event) {
                pcTable._row_actions_checkbox_click.call(pcTable, $(this).closest('tr'), event.shiftKey);
                return false;
            });
            pcTable._table.on('mouseleave', function () {
                $(this).blur();
                return false;
            });

            pcTable._container.on('click', '.row_delete', function () {
                pcTable.rows_delete.call(pcTable, $(this).data('tr'))
            });
            pcTable._container.on('click', '.row_duplicate', function () {
                pcTable.row_duplicate.call(pcTable, $(this).data('tr'))
            });
            pcTable._container.on('click', '.row_refresh', function () {
                pcTable.row_refresh.call(pcTable, $(this).data('tr'))
            });
            pcTable._table.on('click', '.DataRow .id button.edit', function () {
                pcTable.rows_edit.call(pcTable, $(this).closest('tr'))
            });

            if (pcTable.isCreatorView && pcTable.tableRow.type === 'cycles') {
                pcTable._container.on('click', '.cycle_refresh', function () {
                    pcTable.cycle_refresh.call(pcTable, $(this).data('tr'))
                });
            }

        },
        _idCheckButton: $('<button class="btn btn-xxs chbox btn-default" data-action="checkbox"><span class="fa fa-square-o"></span></button>')
        ,
        _checkStatusBar: $('<div class="check-status-bar">✓ <span data-name="count_checked_rows">0</span> из <span data-name="count_visible_rows">0</span></div>')
        ,
        _headCellIdButtonsState: function () {
            "use strict";
            let pcTable = this;

            let checkedRows = this.row_actions_get_checkedIds();

            if (checkedRows.length > 0) {
                $('table.pcTable-table').addClass('with-checks')
            } else {
                $('table.pcTable-table').removeClass('with-checks');
            }

            if (this.dataSortedVisible.length !== this.__checkedRows.length) {
                pcTable._idCheckButton.html('<span class="fa fa-square-o"></span>');
            } else {
                pcTable._idCheckButton.html('<span class="fa fa-check"></span>');
            }

            this._refreshCheckedStatus();
            pcTable.ScrollClasterized.reloadScrollHead.call(pcTable.ScrollClasterized)
        }
        ,
        _addCellId: function (item, $row) {
            let $tdId = $('<td class="id">' + item['id'] + '</td>');
            $tdId.appendTo($row);

            if (item.$checked === true) {
                this.row_actions_icons_add($tdId);
                this.row_actions_check(item, true);
            }
            return $tdId;
        },
        _addCellNo: function (item, $row) {
            let $tdNo = $('<td class="No">--</td>');
            $tdNo.appendTo($row);
            return $tdNo;
        }
        ,
        row_actions_uncheck_all: function () {
            "use strict";
            let pcTable = this;

            let checkedIds = this.row_actions_get_checkedIds();
            for (let i = 0; i < checkedIds.length; i++) {
                let item = pcTable._getItemById(checkedIds[i]);
                pcTable.row_actions_uncheck.call(pcTable, item, true);
            }
            this.__checkedRows = [];

            this._headCellIdButtonsState();
        }
        ,
        _refreshCheckedStatus: function () {
            this._checkStatusBar.find('[data-name="count_checked_rows"]:first').text(this.__checkedRows.length);
            this._checkStatusBar.find('[data-name="count_visible_rows"]:first').text(this.dataSortedVisible.length);
        }
        ,

        _row_actions_checkbox_click: function ($tdId, shiftKey) {
            let pcTable = this;
            let $tr = $tdId.closest('tr');
            let item = this._getItemByTr($tr);
            let LastCheckAction = $.extend({}, (pcTable._lastcheckAction || {}));
            pcTable._lastcheckAction = {id: item.id, isCheck: !item.$checked};


            if (shiftKey) {
                let idsToCheck = [];

                let lastInd;

                if (LastCheckAction.id && (!item.$checked) === LastCheckAction.isCheck && (lastInd = pcTable.dataSortedVisible.indexOf(LastCheckAction.id)) !== -1) {
                    let nowInd = pcTable.dataSortedVisible.indexOf(item.id);
                    if (lastInd < nowInd) {
                        idsToCheck = pcTable.dataSortedVisible.slice(lastInd + 1, nowInd + 1);
                    } else {
                        idsToCheck = pcTable.dataSortedVisible.slice(nowInd, lastInd);
                    }


                } else {
                    pcTable.dataSortedVisible.some(function (id) {
                        if (pcTable.data[id].$checked) idsToCheck = [];
                        else {
                            idsToCheck.push(id);
                        }
                        if (id === item.id) {
                            return true;
                        }
                    });
                }
                if (!item.$checked) {

                    idsToCheck.forEach(function (id) {
                        pcTable.__checkedRows.push(id);
                        pcTable.row_actions_check(pcTable.data[id], true);
                    });
                } else {
                    idsToCheck.forEach(function (id) {
                        pcTable.__checkedRows.splice(pcTable.__checkedRows.indexOf(id), 1);
                        pcTable.row_actions_uncheck(pcTable.data[id], true);
                    });
                }
                this._headCellIdButtonsState();

            } else {
                if (!item.$checked)
                    this.row_actions_check(item);
                else
                    this.row_actions_uncheck(item);
            }


        }
        ,
        row_actions_get_checkedIds: function () {

            return this.__checkedRows;
        }
        ,
        row_actions_check: function (item, isGroupAction) {

            item.$checked = true;

            if (item.$tr) {
                let $tdId = item.$tr.find('.id:first');
                if ($tdId.find('button:first').length === 0) {
                    this.row_actions_icons_add($tdId);
                }
                $tdId.find('.chbox').html('<i class="fa fa-check"/>');
                $tdId.addClass('checked');
                if (!$tdId.is(':hover')) {
                    this.row_actions_icons_hide(item.$tr);
                }
            }

            if (!isGroupAction) {
                this.__checkedRows.push(item.id);
                this._headCellIdButtonsState();
            }

        }
        ,
        row_actions_uncheck: function (item, $isGroupOperation) {
            if (!item.$checked) return;

            item.$checked = false;
            if (item.$tr) {
                $tdId = item.$tr.find('.id');
                $tdId.removeClass('checked');
                $tdId.find('.chbox i').attr('class', 'fa fa-square-o');
                if ($isGroupOperation || !$tdId.is(':hover')) {
                    this.row_actions_icons_hide(item.$tr)
                }
            }

            if (!$isGroupOperation) {
                this.__checkedRows.splice(this.__checkedRows.indexOf(item.id), 1);
                this._headCellIdButtonsState();
            }
        }
        ,
        row_actions_icons_add: function ($tdId) {
            "use strict";
            let $editButton, $dropDownButton;
            let pcTable = this;


            if (this.tableRow.panel) {
                $editButton = $('<button class="btn btn-default edit"><i class="fa fa-th-large"/></button>')
                    .on('mouseleave', function () {
                        $(this).blur();
                        return false;
                    }).css('margin-left', 2);
            } else {
                $editButton = $();
            }

            if (this.control.editing) {
                $dropDownButton = $('<button class="btn btn-default btn-xxs dropdown"  tabindex="-1" style=" margin-left: 2px;">' +
                    '<i class="fa fa-caret-down" style="font-size: 10px; width: 7px;"/></button>');

            }
            let $checkbox = $('<button class="btn btn-default btn-xxs chbox"><i class="fa fa-square-o"/></button>')

            let $btngroup = $('<span class="btn-group-xxs">');
            $tdId.empty()
                .append($btngroup)
                .append(' ')
                .append($checkbox);


            if ($dropDownButton) {
                $btngroup
                    .append($dropDownButton)
            }
            if ($editButton) {
                $btngroup
                    .append($editButton)
            }

        }
        ,
        row_actions_icons_show: function ($tdId) {
            if (!$tdId.is('.checked')) {
                this.row_actions_icons_add($tdId);
            } else {
                $tdId.find('.btn-group-xxs').show();
            }
        }
        ,
        row_actions_icons_hide: function ($tr) {
            let item = this._getItemByTr($tr)
            if (item.$checked) {
                $tr.find('.id').find('.btn-group-xxs').hide();
            } else {
                $tr.find('.id').html(item.id);
            }
        }
        ,
        table_modify: function (json, $trIdBefore, editedObj) {//$trIdBefore - это для вставки дублированных строк
            "use strict";
            let pcTable = this;
            let insertIndex = 0;
            let insertVisibleIndex = 0;
            let editFieldName = editedObj ? editedObj.data('field') : undefined;

            if ($trIdBefore) {
                insertIndex = pcTable.dataSorted.indexOf($trIdBefore) + 1;
                insertVisibleIndex = pcTable.dataSortedVisible.indexOf($trIdBefore) + 1;

            }

            if (json.chdata) {
                let deleted = json.chdata.deleted || [];
                let addedRows = [];

                if (json.chdata.rows) {


                    if (json.refresh && json.chdata.rows) {
                        Object.keys(pcTable.data).forEach(function (id) {
                            if (json.chdata.rows[id] === undefined) {
                                deleted.push(parseInt(id));
                            }
                        });
                    }


                    $.each(json.chdata.rows, function (k, v) {
                        let item = pcTable._getItemById(v.id);
                        if (item === undefined) {
                            addedRows.push(v);
                        } else {
                            pcTable.refreshRow(item.$tr, item, v);
                        }
                    });
                    if (addedRows.length) {
                        if (App.isEmpty(pcTable.data) && pcTable._content) {
                            pcTable._content.find('.pcTable-noDataRow').remove();
                        }
                        $.each(addedRows, function (k, v) {
                            v.$visible = true;

                            v.$checked = false;
                            if (!insertIndex && pcTable.tableRow.with_order_field && !pcTable.tableRow.new_row_in_sort) {
                                v.__inserted = true;
                            }
                            pcTable.data[v.id] = v;

                            let nowInsertInsex = insertIndex;
                            let nowInsertVisibleIndex = insertVisibleIndex;

                            if (v.__after && (!$trIdBefore || $trIdBefore.id !== v.__after)) {
                                nowInsertInsex = pcTable.dataSorted.indexOf(v.__after) + 1;
                                nowInsertVisibleIndex = pcTable.dataSortedVisible.indexOf(v.__after) + 1;
                            }

                            pcTable.dataSorted.splice(nowInsertInsex, 0, v.id);
                            pcTable.dataSortedVisible.splice(nowInsertVisibleIndex, 0, v.id);

                            insertIndex++;
                            insertVisibleIndex++;

                        });


                        if ($trIdBefore) {
                            setTimeout(function () {
                                $.each(addedRows, function (k, v) {
                                    pcTable.row_actions_check(pcTable.data[v.id]);
                                })
                            }, 50);

                        }
                    }
                }


                if (deleted.length) {
                    $.each(deleted, function (k, v) {
                        pcTable._deleteItemById.call(pcTable, v);
                    });
                    if (App.isEmpty(pcTable.data) && pcTable._content&& pcTable._content.find('.' + this.noDataRowClass).length === 0) {
                        pcTable._content.append(pcTable._createNoDataRow());
                    }

                }

                if (deleted.length || addedRows.length || (json.chdata.nsorted_ids && pcTable.nSorted && !Object.equals(json.chdata.nsorted_ids, pcTable.dataSorted))) {

                    if (json.chdata.nsorted_ids && pcTable.nSorted) {
                        let oldVisible = pcTable.dataSortedVisible;
                        pcTable.dataSorted = json.chdata.nsorted_ids;

                        if (oldVisible.length === pcTable.dataSorted.length)
                            pcTable.dataSortedVisible = pcTable.dataSorted
                        else {
                            pcTable.dataSortedVisible = [];
                            pcTable.dataSorted.forEach(function (id) {
                                if (oldVisible.indexOf(id) !== -1)
                                    pcTable.dataSortedVisible.push(id)
                            })
                        }
                    }


                    this.ScrollClasterized.insertToDOM(undefined, true);
                }

                let paramsChanges = {};
                if (json.chdata.params) {
                    $.each(json.chdata.params, function (k, v) {
                        ['v', 'v_', 'f', 'e', 'h', 'c'].forEach(function (part) {
                            if (v[part] !== undefined || pcTable.data_params[k][part]) {
                                if (!Object.equals(pcTable.data_params[k][part], v[part]) || k === editFieldName) {
                                    paramsChanges[k] = true;
                                    pcTable.data_params[k][part] = v[part];
                                }
                            }
                        });
                    });
                }

                if (json.chdata.fields) {
                    $.each(json.chdata.fields, function (k, v) {
                        if (v.list && !Object.equals(pcTable.fields[k].list, v.list)) {
                            paramsChanges[k] = true;
                            $.extend(pcTable.fields[k], v);
                        }
                    });
                }
                if (json.chdata.params || json.chdata.fields) {
                    pcTable._refreshParamsBlock(paramsChanges, true);
                    pcTable._refreshFootersBlock(paramsChanges, true);
                }
                if (json.chdata.f) {
                    let newf = json.chdata.f;
                    ['blockadd', 'blockdelete', 'blockorder', 'background', 'blockduplicate', 'block'].forEach(function (k) {
                        if (newf[k] || pcTable.f[k]) {
                            if (newf[k] !== pcTable.f[k]) {
                                pcTable.f[k] = newf[k];
                                if (pcTable.__formatFunctions[k]) {
                                    pcTable.__formatFunctions[k].call(pcTable);
                                }
                            }
                        }
                    })
                }
                if (App.isEmpty(pcTable.data) && pcTable._content) {
                    if (pcTable._content.find('.' + this.noDataRowClass).length == 0) {
                        pcTable._content.append(this._createNoDataRow());
                    }
                }
            }
            if (json.updated) {
                pcTable.model.tableData.updated = JSON.parse(json.updated)
                pcTable._refreshTitle();
            }
            if (json.filtersString) {
                pcTable._refreshFiltersBlock.call(pcTable, json)
            }
            pcTable._headCellIdButtonsState();
        }
        ,
        rows_edit: function ($tr) {
            "use strict";
            let pcTable = this;
            let checkedRows = this.row_actions_get_checkedIds();

            if ($tr && (checkedRows.indexOf(pcTable._getItemByTr($tr).id) === -1)) {
                this.row_actions_check(pcTable._getItemByTr($tr));
                checkedRows = this.row_actions_get_checkedIds();
            }
            pcTable._row_edit.call(pcTable, checkedRows.slice());
            return false;
        }
        ,
        row_dropdown: function ($dropDownButton) {
            "use strict";

            let pcTable = this;
            let text = $('<div>');
            let item = pcTable._getItemByTr($dropDownButton.closest('tr'));
            let trId = item.id;


            if (this.control.duplicating !== true || pcTable.f.blockduplicate || item.f.blockduplicate) {
                text.append($('<div class="menu-item"><i class="fa fa-clone"/> Дублировать</div>').css('color', 'gray'));
            } else {
                text.append($('<div class="menu-item row_duplicate"><i class="fa fa-clone"/> Дублировать</div>').attr('data-tr', trId));
            }

            if (['calcs', 'globcalcs'].indexOf(pcTable.tableRow.type) !== -1) {
                text.append($('<div class="menu-item"><i class="fa fa-refresh"/> Пересчитать</div>').css('color', 'gray'));
            } else {
                text.append($('<div class="menu-item row_refresh"><i class="fa fa-refresh"/> Пересчитать</div>').attr('data-tr', trId));
            }

            if (pcTable.isCreatorView && pcTable.tableRow.type === 'cycles') {
                text.append($('<div class="menu-item cycle_refresh color-danger"><i class="fa fa-refresh"/> Пересчитать цикл</div>').attr('data-tr', trId));
            }


            if (!this.control.deleting || this.f.blockdelete || (item.f && (item.f.block || item.f.blockdelete))) {
                text.append($('<div class="menu-item"><i class="fa fa-times"/> Удалить</div>').css('color', 'gray'));
            } else {
                text.append($('<div class="menu-item row_delete"><i class="fa fa-times"/> Удалить</div>').attr('data-tr', trId));
            }

            let popoverId = App.popNotify({
                isParams: true,
                $text: text,
                element: $dropDownButton,
                container: this._container,
                trigger: 'manual',
                placement: 'bottom'
            });

            $('#' + popoverId).position({
                my: "left top",
                at: "left-3px bottom+10px",
                of: $dropDownButton
            }).off().on('mouseleave', function () {
                text.remove();
                pcTable.row_actions_icons_hide($dropDownButton.closest('tr'));
            }).find('.arrow').css('left', '11px').end()
                .find('.popover-content').css('padding', '5px');


            return false;
        }
        ,
        __getCheckedRowsIds: function (trId, checkBlockedRows, elseblockparam) {
            "use strict";
            let pcTable = this;
            let checkedRows = this.row_actions_get_checkedIds();
            if (trId && (checkedRows.indexOf(trId) === -1)) {
                let item = pcTable.data[trId];
                this.row_actions_check(item);
                checkedRows = this.row_actions_get_checkedIds();
            }
            if (checkBlockedRows) {
                let isBlockedRow = false;
                checkedRows.some(function (id) {
                    if (pcTable.data[id].f && (pcTable.data[id].f.block || pcTable.data[id].f[elseblockparam])) {
                        isBlockedRow = pcTable.data[id];
                        return true;
                    }
                });

                if (isBlockedRow) {
                    let mainBlockField = '';

                    if (pcTable.mainFieldName !== 'id') {
                        if (isBlockedRow[pcTable.mainFieldName]._v) {
                            mainBlockField = isBlockedRow[pcTable.mainFieldName]._v;
                        } else {
                            mainBlockField = isBlockedRow[pcTable.mainFieldName].v;
                        }
                        mainBlockField = ' "' + mainBlockField + '"';
                    }
                    let $ntf = $("<span>").html('Строка <b>id ' + isBlockedRow.id + '</b>');
                    if (mainBlockField !== '') {
                        let b = $ntf.find('b');
                        b.text(b.text() + mainBlockField);
                    }
                    $ntf.append(' заблокирована');
                    App.notify($ntf, 'Действие не выполнено');

                    return false;
                }
            }
            return checkedRows;

        }
        ,
        row_refresh: function (trId) {
            "use strict";
            let pcTable = this;
            let checkedRows = this.__getCheckedRowsIds(trId, false);
            if (checkedRows && checkedRows.length) {
                let buttons = [
                    {
                        label: 'Пересчитать',
                        action: function (dialogRef) {
                            pcTable.model.refresh_rows(checkedRows).then(function (json) {
                                pcTable.table_modify.call(pcTable, json);
                                dialogRef.close();
                                pcTable.row_actions_uncheck_all();
                            });

                        }
                    },
                    {
                        label: 'Отмена',
                        action: function (dialogRef) {
                            dialogRef.close();
                        }

                    }
                ];

                BootstrapDialog.show({
                    message: 'Точно пересчитать ' + checkedRows.length + ' строк?',
                    title: 'Пересчет',
                    buttons: buttons,
                    draggable: true
                })
            }
        },
        cycle_refresh: function (trId) {
            "use strict";
            let pcTable = this;
            let checkedRows = this.__getCheckedRowsIds(trId, false);
            if (checkedRows && checkedRows.length) {
                let buttons = [
                    {
                        label: 'Пересчитать',
                        action: function (dialogRef) {
                            pcTable.model.refresh_cycles(checkedRows).then(function (json) {
                                pcTable.table_modify.call(pcTable, json);
                                dialogRef.close();
                                pcTable.row_actions_uncheck_all();
                            });

                        }
                    },
                    {
                        label: 'Отмена',
                        action: function (dialogRef) {
                            dialogRef.close();
                        }

                    }
                ];

                BootstrapDialog.show({
                    message: 'Точно пересчитать ' + checkedRows.length + ' циклов?',
                    title: 'Пересчет',
                    buttons: buttons,
                    draggable: true
                })
            }
        }
        ,
        row_duplicate: function (trId) {
            "use strict";

            let pcTable = this;
            let checkedRows = this.__getCheckedRowsIds(trId, false);
            if (checkedRows && checkedRows.length) {
                let buttons = [
                    {
                        label: 'Дублировать',
                        cssClass: 'btn-danger',
                        action: function (dialogRef_main) {
                            let unic_replaces = {};
                            let unic_fields = [];

                            let newCheckedRows = [];
                            pcTable.dataSortedVisible.forEach(function (id) {
                                if (checkedRows.indexOf(id) !== -1) {
                                    newCheckedRows.push(id);
                                }
                            });
                            checkedRows = newCheckedRows;

                            const duplicate = function (dialogRef) {
                                pcTable.model.duplicate(checkedRows, unic_replaces, trId).then(function (json) {
                                    pcTable.table_modify.call(pcTable, json, trId);
                                    if (dialogRef)
                                        dialogRef.close();
                                    dialogRef_main.close();
                                    pcTable.row_actions_uncheck_all();
                                });
                            };

                            for (let i in pcTable.fieldCategories.column) {
                                let field = pcTable.fieldCategories.column[i];
                                if (field.type === 'unic') {
                                    unic_fields.push(field.name);
                                }
                            }

                            //Замена значений уникальных полей
                            if (unic_fields.length) {
                                let $uniqTable = $('<table class="simpleTable"><thead><tr><td class="id">id</td></tr></thead><tbody></tbody></table>');
                                let $head = $uniqTable.find('thead tr');
                                let $body = $uniqTable.find('tbody');
                                let mainField;
                                if (pcTable.mainFieldName !== 'id') {
                                    mainField = pcTable.fields[pcTable.mainFieldName];
                                    if (mainField.type !== 'unic') {
                                        $head.append($('<td></td>').text(mainField.title));
                                    } else {
                                        mainField = null;
                                    }
                                }
                                for (let i in unic_fields) {
                                    let field = pcTable.fields[unic_fields[i]];
                                    $head.append($('<td></td>').text(field.title));
                                }

                                for (let i in checkedRows) {
                                    let id = checkedRows[i];
                                    let row = pcTable.data[id];
                                    let tr = $('<tr>');

                                    unic_replaces[id] = {};

                                    tr.append($('<td class="id"></td>').text(id));
                                    if (mainField) {
                                        tr.append($('<td></td>').text(row[mainField.name]['v']));
                                    }
                                    for (let i in unic_fields) {
                                        let field = pcTable.fields[unic_fields[i]];
                                        let tdInput = $('<td class="input"></td>');
                                        let input = $('<input>').val(row[field.name].v);
                                        unic_replaces[id][field.name] = row[field.name].v;
                                        let timeoutObject;

                                        input.on('keyup', function () {
                                            let val = $(this).val();

                                            unic_replaces[id][field.name] = val;

                                            if (timeoutObject) {
                                                clearTimeout(timeoutObject);
                                                timeoutObject = null;
                                            }

                                            if (val === '') {
                                                if (field.required) {
                                                    tdInput.removeClass('ok');
                                                } else {
                                                    tdInput.addClass('ok');
                                                }
                                                return;
                                            } else {
                                                timeoutObject = setTimeout(function () {
                                                    pcTable.model.checkUnic(field.name, val).then(function (json) {
                                                        if (json.ok) tdInput.addClass('ok');
                                                        else {
                                                            tdInput.removeClass('ok');
                                                        }
                                                    });
                                                }, 300)
                                            }

                                        });

                                        tdInput.html(input);
                                        tr.append(tdInput);
                                    }
                                    $body.append(tr);
                                }


                                let buttons = [
                                    {
                                        label: 'Дублировать',
                                        cssClass: 'btn-m btn-warning',
                                        action: function (dialogRef) {
                                            duplicate(dialogRef);
                                        }
                                    },
                                    {
                                        label: 'Отмена',
                                        action: function (dialog2) {
                                            dialog2.close();
                                            dialogRef_main.close();
                                        }
                                    }
                                ];


                                BootstrapDialog.show({
                                    message: $uniqTable,
                                    title: 'Заполните значения для уникальных полей',
                                    buttons: buttons,
                                    draggable: true
                                })
                            } else {
                                duplicate();
                            }
                        }
                    }, {
                        label: 'Отмена',
                        action: function (dialogRef) {
                            dialogRef.close();
                        }

                    }
                ];

                BootstrapDialog.show({
                    message: 'Точно дублировать ' + checkedRows.length + ' строк?',
                    title: 'Дублирование',
                    buttons: buttons,
                    draggable: true
                })
            }
        }
        ,
        rows_delete: function (trId) {
            let pcTable = this;
            let checkedRows = this.__getCheckedRowsIds(trId, true, 'blockdelete');
            if (checkedRows && checkedRows.length) {

                let $message = 'Точно удалить ' + checkedRows.length + ' строк?';
                let $messageTimer = 'Удаление ' + checkedRows.length + ' строк?';
                if (checkedRows.length == 1) {
                    let item = 'id-' + checkedRows[0];
                    if (pcTable.mainFieldName != 'id') {
                        item = pcTable.data[checkedRows[0]][pcTable.mainFieldName];
                        item = 'id-' + checkedRows[0] + ' "' + (item.v_ && item.v_[0] ? item.v_[0] : item.v) + '"';
                    }
                    $message = 'Точно удалить строку ' + item + '?';
                    $messageTimer = 'Удаление строки ' + item + '?';
                }


                let buttons = [
                    {
                        label: 'Удалить',
                        cssClass: 'btn-danger',
                        action: function (dialogRef) {
                            dialogRef.close();

                            const deleteIt = function () {
                                pcTable.model.delete(checkedRows).then(function (json) {
                                    pcTable.table_modify.call(pcTable, json);
                                });
                            };
                            if (pcTable.tableRow.delete_timer > 0) {
                                App.panelTimer($messageTimer, pcTable.tableRow.delete_timer, deleteIt)
                            } else {
                                deleteIt();
                            }
                        }
                    }, {
                        label: 'Отмена',
                        action: function (dialogRef) {
                            dialogRef.close();
                        }

                    }
                ];


                BootstrapDialog.show({
                    message: $message,
                    title: 'Удаление',
                    buttons: buttons,
                    draggable: true
                })
            }
        }
    });
})
(window, jQuery);

$.extend(App.pcTableMain.prototype, {
    _insertItem: null,
    _insertRow: null,
    _currentInsertCellIndex: 0,
    _addInsert: function (addVars) {
        var pcTable = this;
        if (this.control.adding) {
            if (!this._insertRow || !this._insertRow.length) {
                if (addVars) {
                    this._insertItem = {};

                    this.fieldCategories.column.forEach(function (field) {
                        if (addVars[field.name]) {
                            pcTable._insertItem[field.name] = {v: addVars[field.name]};
                        }
                    })

                }

                this._insertRow = this._createInsertRow(null, 0);

                this._beforebody.prepend(this._insertRow);
                this._table.addClass('with-adding-row');
            }
        }
    },
    _InsertAddInsertBtnsPanel: function ($row) {
        let pcTable = this;
        let panel;

        let btns = {};
        btns[('<span id="saveInsertRow" tabindex="' + (pcTable.fieldCategories.column.length) + '">Сохранить</span>')] =
            function () {
                panel.addClass('onSaving');

                pcTable.__insertRowActions('saveInsertRow', function () {
                    pcTable._saveInsertRow.call(pcTable, 'close').always(function () {
                        panel.removeClass('onSaving');
                    });
                });

            };
        btns[('<i class="fa fa-save"  tabindex="' + (pcTable.fieldCategories.column.length + 1) + '"/>')] =
            function () {
                panel.addClass('onSaving');
                pcTable.__insertRowActions('saveInsertRow', function () {
                    pcTable._saveInsertRow.call(pcTable).then(function () {
                        panel.removeClass('onSaving');
                    });
                });

            };
        btns[('<i class="fa fa-paste"  tabindex="' + (pcTable.fieldCategories.column.length + 2) + '"/>')] =
            function () {
                panel.addClass('onSaving');
                pcTable.__insertRowActions('saveInsertRow', function () {
                    pcTable._saveInsertRow.call(pcTable, 'notClean').then(function () {
                        panel.removeClass('onSaving');
                    });
                });

            };
        btns['<i class="fa fa-times" tabindex="' + (pcTable.fieldCategories.column.length + 3) + '"/>'] = function () {
            pcTable._closeInsertRow.call(pcTable, $(this).closest('#' + pcTable_PANNEL_IDS.insert));

        };

        panel = this._addRowPanel(pcTable_PANNEL_IDS.insert, $row, btns);
    },
    __insertRowActionsStack: [],
    __insertRowActions: function (actionName, actionFunction) {
        "use strict";
        let pcTable = this;

        if (['saveInsertRow', 'clickSourceButton'].indexOf(actionName) !== -1) {
            setTimeout(function () {
                let $d;
                if ($d = pcTable.model.getDefferedProcess()) {
                    $d.then(actionFunction);
                } else actionFunction();
            }, 450)
        }

    },
    _saveInsertRow: function (isNotClean) {
        let pcTable = this;
        let data = {};
        let $d = $.Deferred();
        let doIt = function () {
            $.each(pcTable._insertItem, function (k, v) {
                if (k!=='n')  data[k] = v.v;
            });

            pcTable.model.add(data).then(function (json) {
                pcTable.table_modify.call(pcTable, json);

                pcTable._currentInsertCellIndex = 0;
                switch (isNotClean) {
                    case 'notClean':
                        break;
                    case 'close':
                        pcTable._closeInsertRow();
                        break;
                    default:
                        pcTable._insertItem = null;
                        pcTable._insertRow.html('<td class="id"></td>');
                        pcTable._createInsertRow(pcTable._insertRow, 0);
                }


            }).always(function () {
                $d.resolve();
            });
        };
        pcTable.model.doAfterProcesses(doIt);
        return $d.promise();
    },
    _getInsertButtons: function () {
        let pcTable = this;
        let buttons = $('<span>');

        let AddWithRow = function () {
            pcTable._addInsert.call(pcTable)
        };

        let AddWithPanel = function () {
            pcTable._addInsertWithPanel.call(pcTable)
        };

        if (this.tableRow['type'] === 'cycles') {
            AddWithPanel = AddWithRow = function () {
                pcTable.model.add({}).then(function (json) {
                    if (json.firstTableId) {
                        window.location.href = window.location.pathname + '/' + json.chdata.rows[0].id + '/' + json.firstTableId;
                    } else {
                        pcTable.table_modify.call(pcTable, json);
                    }
                });
            }
        }

        $('<button data-action="add" class="btn btn-warning btn-sm">Добавить</button>').width(80)
            .on('click', AddWithRow).appendTo(buttons);
        if (this.tableRow.panel) {
            $('<button class="btn btn-warning btn-sm"><i class="fa fa-th-large"/></button>')
                .on('click', AddWithPanel).appendTo(buttons).css('margin-left', 5);
        }
        return buttons;
    },
    _addInsertWithPanel: function () {
        let pcTable = this;
        new EditPanel([this.tableRow.id,this.model.tableData.sess_hash], null, {}).then(function (json) {
            if(json){
                pcTable.table_modify.call(pcTable, json);
            }
        });
    },
    _closeInsertRow: function () {
        if (!this._insertPanel) {
            this._insertRow.find('td').each(function () {
                $(this).remove();
            });
            this._insertRow.remove();
            this._insertRow = null;

        }
        else {
            this._insertPanel = null;
        }
        this._insertItem = null;
        this._currentInsertCellIndex = 0;
        this._table.removeClass('with-adding-row');
    },
    _createInsertRow: function ($row, focusIt, savedFieldName) {
        var pcTable = this;
        var item = pcTable._insertItem || (pcTable._insertItem = {});

        if (!$row) {
            this.insertRow = $row = $('<tr class="InsertRow" style="height: 35px;"><td class="id"></td></tr>');
            this._InsertAddInsertBtnsPanel($row);
        }

        if (!pcTable._currentInsertCellIndex) pcTable._currentInsertCellIndex = 0;
        let data = {};
        $.each(pcTable._insertItem, function (k, v) {
            if (k!=='n') data[k] = v.v;
        });

        let visibleColumnsIndexes = [];
        pcTable.fieldCategories.visibleColumns.forEach(function (field) {
            visibleColumnsIndexes.push(field.name);
        });

        pcTable.model.checkInsertRow(data, savedFieldName).then(function (json) {

            item = json.row;

            $.each(pcTable.fieldCategories.column, function (ind, field) {

                if (!field.showMeWidth) {
                     pcTable._insertItem[field.name] = item[field.name];
                    return;
                }

                let index = visibleColumnsIndexes.indexOf(field.name);


                var td = $row.find('td:eq(' + (index + 1) + ')');
                let Oldval = pcTable._insertItem[field.name];
                let isForce = pcTable._insertItem[field.name] && pcTable._insertItem[field.name].force;

                    pcTable._insertItem[field.name] = item[field.name];

                if (td.length) {

                    let isBlockedField = field.name === 'n' || pcTable._insertItem[field.name].f && pcTable._insertItem[field.name].f.block === true;

                    if (td.data('input') && !isBlockedField) {

                        let name = field.name;
                        let isEqual = false;

                        if (isForce) {
                            isEqual = false;
                        }
                        else if (item[field.name].v === null && Oldval.v == '') {
                            isEqual = true;
                        }
                        else {
                            isEqual = Object.equals(item[field.name].v, Oldval.v) && !field.codeSelectIndividual;
                        }

                        if ((Oldval === undefined || !isEqual || field.name == 'data_src' || field.type == 'comments')) {
                            pcTable._createInsertCell.call(pcTable, td, field, $row, index, 'td', pcTable._createInsertRow);
                            if (savedFieldName === field.name) {
                                pcTable._colorizeElement(td, pcTable_COLORS.saved);
                            }
                        }
                    }
                    else {
                        td.replaceWith(pcTable._createInsertCell.call(pcTable, null, field, $row, index, 'td', pcTable._createInsertRow));
                    }
                } else {
                    $row.append(td = pcTable._createInsertCell.call(pcTable, null, field, $row, index, 'td', pcTable._createInsertRow));
                }

            });
            pcTable._insertFocusIt.call(pcTable);
        });


        return $row;
    },
    _createInsertCell: function (td, field, row, index, nodeName, parentFunction) {


        nodeName = nodeName || 'td';
        var td = td || $("<" + nodeName + ">");
        var pcTable = this;

        if (field.code) {
            td.addClass('with-code');
        }


        if (pcTable._insertItem[field.name] === undefined) {
            pcTable._insertItem[field.name] = null;
        }

        if (!field.insertable || (pcTable._insertItem[field.name].f && pcTable._insertItem[field.name].f.block == true)) {
            let val = pcTable._insertItem[field.name];
            if (val) val = val.v;
            td.empty().append(field.getCellText(val, td, pcTable._insertItem));
            return td;
        }

        var getEditVal = function ($input) {

            var editVal
            try {
                editVal = field.getEditVal($input);
            }
            catch (error) {
                App.popNotify(error, $input, 'default');
                return null;
            }

            return editVal;
        };


        var saveClbck = function ($input, event) {
            var editValResult = getEditVal($input);
            if (editValResult === null) {
                pcTable._insertFocusIt.call(pcTable)
            } else {
                pcTable._currentInsertCellIndex = index + 1;
                if (field.isDataModified(editValResult, pcTable._insertItem[field.name].v)) {
                    pcTable._insertItem[field.name].v = editValResult;
                    if (field.isPanelField === true) {
                        pcTable._createInsertCell.call(pcTable, td, field, row, index, nodeName, parentFunction);
                    }
                    parentFunction.call(pcTable, row, pcTable._currentInsertCellIndex, field.name);
                }
                else {
                    pcTable._insertFocusIt.call(pcTable)
                }
            }

        };
        var blurClbck = function ($input, event) {
            setTimeout(function () {

                let td = $input.closest('td');
                if (!td.length || !td.closest('tr').length) return false;

                let editValResult = getEditVal($input);
                if (editValResult === null) {
                    pcTable._insertFocusIt.call(pcTable)
                } else {

                    if (field.isDataModified(editValResult, pcTable._insertItem[field.name].v)) {
                        pcTable._insertItem[field.name].v = editValResult;
                        if (field.isPanelField === true) {
                            pcTable._createInsertCell.call(pcTable, td, field, row, index, nodeName, parentFunction);
                        }
                        parentFunction.call(pcTable, row, pcTable._currentInsertCellIndex, field.name);
                    }
                }
            }, 150)
        };
        var escClbck = function ($input, event) {
            let td = $input.closest('td');
            if (!td.length || !td.closest('tr').length) return false;

            let editVal = getEditVal($input);
            let itemVal = pcTable._insertItem[field.name].v + "";

            if (field.isDataModified(editVal, itemVal)) {
                pcTable._createInsertCell(td, field, row, index, nodeName, parentFunction);
                pcTable._colorizeElement(td, pcTable_COLORS.blured);
            }
        };

        let input = field.getEditElement(td.data('input'), pcTable._insertItem[field.name], pcTable._insertItem, saveClbck, escClbck, blurClbck);

        td.on('click focus', 'input,button,select', function () {
            pcTable._currentInsertCellIndex = index;
        });
        td.on('click focus', function () {
            pcTable._currentInsertCellIndex = index;
        });

        if (!input.isAttached()){
            td.html(input).data('input', input);
        }

        if (field['type'] === 'select' && field.changeSelectTable === 2) {
            td.addClass('with-source-add-button');
            let btn = $('<button class="btn btn-default btn-sm source-add" tabindex="-1"><i class="fa fa-plus"></i></button>');

            td.prepend(btn);
            let clickSourceButton = function () {
                let ee = {};
                let item = pcTable._insertItem;

                $.each(item, function (k, v) {
                    if (k.substring(0, 1) !== '$') {
                        ee[k] = v;
                    }
                });
                let isAdd = true;
                if (isAdd) {
                    ee[field.name] = null;
                }
                let opened = 0;
                $(window.top.document.body)
                    .on('pctable-opened.select-add-' + field.name, function () {
                        opened++;
                    })
                    .on('pctable-closed.select-add-' + field.name, function (event, data) {
                        opened--;
                        let isAdded = (data /*&& data.tableId === field.selectTableId*/ && data.method === 'insert' && data.json && data.json.chdata && data.json.chdata.rows);
                        if (opened === 0 || isAdded) {
                            let inputOld = input;
                            delete field.list;
                            if (inputOld.data('input').data('LISTs')){
                                inputOld.data('input').data('LISTs').isListForLoad = true;
                            }
                            if (isAdded) {
                                if (field.multiple) {
                                    item[field.name].v.push(Object.keys(data.json.chdata.rows)[0]);
                                } else {
                                    item[field.name].v = Object.keys(data.json.chdata.rows)[0];
                                }
                            }
                            inputOld.replaceWith(input = field.getEditElement(inputOld, item[field.name], item, saveClbck, escClbck, blurClbck));
                            $('body').off('.select-add-' + field.name);
                            td.data('input', input);
                            parentFunction.call(pcTable, row, pcTable._currentInsertCellIndex, field.name);

                        }
                    });
                pcTable.model.selectSourceTableAction(field.name, ee);
                return false;
            };
            btn.on('click', function () {
                pcTable.__insertRowActions('clickSourceButton', clickSourceButton);
            });
        }

        return td;
    },
    _insertFocusIt: function (outTimed) {
        let pcTable = this;
        if (!outTimed) {

            setTimeout(function () {
                pcTable._insertFocusIt.call(pcTable, 1);
            }, 10)
            return false;
        }


        let isLastCell = true;
        let isPanel = this._insertPanel ? true : false;
        let $row = this.insertRow;
        if (isPanel) {
            if (pcTable._insertPanel) {
                $row = pcTable._insertPanel.$modalBody;
            } else {
                return false;
            }
        }

        if (!$row || !$row.length) return false;

        $.each(pcTable.fieldCategories.visibleColumns, function (index, field) {
            if (pcTable._currentInsertCellIndex == index) {
                if (!field.insertable || (pcTable._insertItem && pcTable._insertItem[field.name] && pcTable._insertItem[field.name].f && pcTable._insertItem[field.name].f.block === true)) {
                    pcTable._currentInsertCellIndex++;
                    return;
                }
                else {
                    if (isPanel) {
                        field.focusElement($row.find('.cell:eq(' + index + ')').data('input'));
                    }
                    else {
                        field.focusElement(pcTable._getTdByColumnIndex($row, index + 1).data('input'));
                    }
                }
                isLastCell = false;
                return false;
            }
        });
        if (isLastCell) {
            if (isPanel) {
                let buttonSave = pcTable._insertPanel.indexedButtons[Object.keys(pcTable._insertPanel.indexedButtons)[0]];
                buttonSave.focus();
            } else {
                $('#saveInsertRow').parent().focus();
            }
        }
    }
});
$.extend(App.pcTableMain.prototype, {
    _rerenderColumnsFooter: function () {
        let footers = this._createFootersBlock();
        this._footersBlock.replaceWith(footers[0]);
        this._footersBlock = footers[0];
    },
    _renderTable: function () {
        let pcTable = this;

        //Предотвращение автозаполнения в хроме
        /* $('body').append('<input style="opacity: 0;position: absolute; top:0">\n' +
             '<input type="password" style="opacity: 0;position: absolute; top:0">\n');
 */

        this._table = $("<table>")
            .addClass(this.tableClass);

        if (this.notCorrectOrder) {
            this._table.addClass('no-correct-n-filtered')
        }

        this._table.append(this._createHead())
            .append(this._createFirstBody())
            .append(this._createBody())
            .append(this._createAfterBody())

        let footers = this._createFootersBlock();
        this._footersBlock = footers[0];
        this._footersSubTable = footers[1];

        this._table.append(this._footersBlock);
        this._popovers = $('<div class="popovers">');


        if (this.fieldCategories.column.length === 1) {
            pcTable._container.addClass('no-fields');
        }

        let scrollWrapper = this.scrollWrapper = this._container.append('<div class="pcTable-scrollwrapper">').find('.pcTable-scrollwrapper');
        scrollWrapper
            .append(this._createBeforeSpace());
        if (this.isCreatorView) {
            scrollWrapper
                .append(this._refreshHiddenFieldsBlock())
        }
        scrollWrapper.append(this._paramsBlock = this._createParamsBlock())
            .append(this._createFiltersBlock());


        scrollWrapper
            .append($('<div class="pcTable-buttonss">').append(this._rowsButtons()))
            .append(this._innerContainer)
            .append(this._footersSubTable)
            .append(this._popovers);

        pcTable._container.height(window.innerHeight - pcTable._container.offset().top - 20);

        this.addScrollsRules();

        this._seeCalcucatedValData();
        this._seeSelectPreview();
        this._clickstoCopyMe();

        if (this.isCreatorView) {
            //this._clicksToCodeView();
        }

    },
    _refreshHiddenFieldsBlock: function () {
        let newBlock = this._hiddenFieldsBlock();
        if (this.HiddenFieldsBlock) {
            this.HiddenFieldsBlock.replaceWith(newBlock);
        }
        this.HiddenFieldsBlock = newBlock;
        return this.HiddenFieldsBlock;
    },
    _hiddenFieldsBlock: function () {
        let pcTable = this, countFields = 0;

        let block = $('<div class="pcTable-hiddenFieldsTables">'), width = 0, $table, $thead, $tbody;
        let ContainerWidth = this._container.width() - 100;
        $.each(pcTable.hidden_fields || [], function (k, field) {
            countFields++;

            let fWidth = field.width > 0 ? field.width : 100;
            if (width === 0 || (ContainerWidth < (width + fWidth))) {
                if ($table) $table.width(width);

                $table = $("<table class='pcTable-hiddenFieldsTable'><thead><tr></tr></thead></table>\"");
                block.append($table);
                width = 0;
                $thead = $table.find('thead tr');
            }
            $thead.append(pcTable._createHeadCell(k, field));
            width += field.width;
        });

        if (pcTable.isCreatorView) {
            Object.keys(pcTable.fields).forEach(function (fieldName, k) {
                let field = pcTable.fields[fieldName];
                if (field.showInWeb && field.showMeWidth < 1 && field.name !== 'n') {
                    countFields++;

                    let fWidth = field.width > 0 ? field.width : 100;
                    if (width === 0 || (ContainerWidth < (width + fWidth))) {
                        if ($table) $table.width(width);

                        $table = $("<table class='pcTable-hiddenFieldsTable'><thead><tr></tr></thead></table>\"");
                        block.append($table);
                        width = 0;
                        $thead = $table.find('thead tr');
                    }
                    $thead.append(pcTable._createHeadCell(k, field));
                    width += field.width;
                }
            });
        }

        if ($table) $table.width(width);
        if (countFields) return block;

        return $('<div class="pcTable-hiddenFieldsTables">');
    },
    _clickstoCopyMe: function () {
        this._container.on('click', '.copy_me', function () {
            let button = $(this);

            if (button.data('clicked')) return false;

            button.data('clicked', true);

            button.width(button.width());

            if (button.data('text')) {
                App.copyMe(button.data('text'));
            } else {
                App.copyMe(button.text());
            }
            button.button('copied');
            setTimeout(function () {
                button.button('reset');
                button.removeData('clicked');
            }, BUTTONS_TIMEOUT);
            button.blur();
            return false;
        })
    },
    _clicksToCodeView: function () {
        let pcTable = this;
        this._container.on('click', 'th .roles', function () {
                let img = $(this);
                if (img.hasClass('.fa-sun-o') || img.hasClass('fa-certificate')) {

                    let field = pcTable._getFieldBytd(img.closest('th'));

                    let newCodemirrorDiv = $('<div class="HTMLEditor" id="bigOneCodemirror" style="height: 100%;"></div>');

                    let editorMax;

                    BootstrapDialog.show({
                        message: newCodemirrorDiv,
                        type: null,
                        title: 'Просмотр кода поля ' + field.title,

                        cssClass: 'fieldparams-edit-panel',
                        draggable: true,
                        onhide: function (event) {
                            mirror.setValue(editorMax.getValue());
                        },

                        onshow: function (dialog) {
                            dialog.$modalHeader.css('cursor', 'pointer');
                            dialog.$modalContent.css({
                                width: "90vw",
                                minHeight: "90vh"
                            });

                        },
                        onshown: function (dialog) {
                            editorMax = CodeMirror(newCodemirrorDiv.get(0), {
                                mode: 'totum',
                                value: field.code[0],
                                theme: 'eclipse',
                                lineNumbers: true,
                                indentWithTabs: true,
                                autoCloseTags: true,
                                bigOneDialog: dialog,
                                readOnly: true
                            });

                            if (mirror.table) editorMax.table = mirror.table;

                            let minheight = Math.round(dialog.$modalContent.height() - dialog.$modalHeader.outerHeight() - 40);
                            editorMax.getScrollerElement().style.minHeight = minheight + 'px';
                            newCodemirrorDiv.find('.CodeMirror').css('min-heught', minheight);
                            editorMax.focus();
                            dialog.$modalContent.position({
                                my: 'center top',
                                at: 'center top+30px',
                                of: window
                            });
                        }
                    })
                }
            }
        )
    },
    _seeCalcucatedValData: function () {
        var pcTable = this;
        this._container.on('mouseover', 'td .fa-hand-paper-o', function () {
            var hand = $(this);
            var td = hand.closest('td');
            var item = pcTable._getItemBytd(td);
            var field = pcTable._getFieldBytd(td);

            var notify = $('<div>');
            let cellText = '';
            if (item[field.name].c === null || item[field.name].c === '' || item[field.name].c === undefined) {
                cellText = '';
            } else {
                if (field.type === 'select') {
                    if (field.multiple) {
                        $.each(item[field.name].c, function (k, v_) {
                            if (cellText !== '') cellText += ', ';
                            cellText += field.getElementString(item[field.name].c[k], item[field.name].c_[k]);
                        })
                    } else {
                        cellText = field.getElementString(item[field.name].c, item[field.name].c_);
                    }

                } else {
                    cellText = field.getCellText(item[field.name].c, null, item, pcTable);
                    if (typeof cellText === 'object') cellText = cellText.text();
                }
            }
            notify.append($('<div>Расчетное значение: </div>').append($('<code>').text(cellText)));

            hand.one('mouseout', function () {
                if (notify.length) {
                    notify.remove();
                    notify = null;
                }
            });
            setTimeout(
                function () {
                    if (notify && notify.length) {
                        App.popNotify(notify, hand);
                    }
                }, 500
            )

        })
    },
    _seeSelectPreview: function () {
        var pcTable = this;
        this._container.on('mouseover', '.select-with-preview li', function (event) {
            let element = $(this);
            let timeObject = setTimeout(function () {
                if (element.is(':hover')) {
                    let span = element.find('span.select-with-preview');
                    if (pcTable.fields[span.data('field')]) {
                        pcTable.fields[span.data('field')].previewPanel.call(pcTable.fields[span.data('field')], span, element);
                    }
                }
            }, 300);
            element.one('mouseout', function () {
                if (timeObject) clearTimeout(timeObject);
            });
        })
    },

    _createBeforeSpace: function () {
        let pcTable = this;
        if (pcTable.beforeSpaceHide) {
            return this._beforeSpace = $('<span></span>');
        }
        this._beforeSpace = $('<div class="pcTable-beforeSpace">');
        pcTable.LogButton = $();

        let topButtons = $('<div class="pcTable-topButtons">');

        let $TOTUM_FOOTER = $('#TOTUM_FOOTER');
        if ($TOTUM_FOOTER.length) {
            topButtons.append($TOTUM_FOOTER);
        }
        if (pcTable.isCreatorView) {
            let LogButtons = $('<div class="creator-log-buttons">');

            let btn = $('<button class="btn btn-danger btn-sm"><i class="fa" style="width: 12px"></i> Показать логи</button>')
                .appendTo(LogButtons)
                .on('click', function () {
                    let $div;

                    const apply = function () {
                        let codes = [];
                        $div.find('input:checked').each(function (i, input) {
                            codes.push($(input).attr('name'));
                        });
                        if (codes.length > 0) {
                            btn.find('i').attr('class', 'fa fa-check-square-o')
                        } else {
                            btn.find('i').attr('class', 'fa fa-square-o')
                        }
                        $.cookie('pcTableLogs', JSON.stringify(codes), {path: '/'});

                        pcTable.FullLOGS = [];
                        pcTable.LOGS = {};

                        btn.popover('destroy');
                    };
                    if (btn.is('[aria-describedby]')) {
                        $div = $('#' + btn.attr('aria-describedby'));
                        apply();
                    } else {
                        let codes = $.cookie('pcTableLogs') || '[]';
                        codes = JSON.parse(codes);
                        $div = $('<div>');
                        $div.append('<div><input type="checkbox" name="c"/> Код</div>');
                        $div.append('<div><input type="checkbox" name="a"/> Код-действия</div>');
                        $div.append('<div><input type="checkbox" name="s"/> Селекты</div>');
                        $div.append('<div><input type="checkbox" name="f"/> Форматирование</div>');
                        $div.append('<div><input type="checkbox" name="recalcs"/> Пересчеты и селекты</div>');

                        let $calcFieldsLogBtn = $('<button class="btn btn-xs"><i class="fa fa-table"></i></button>').on('click', function () {
                            if (!pcTable.FieldLOGS) App.notify('Лог расчета полей пуст');
                            else {
                                pcTable.model.calcFieldsLog(JSON.stringify(pcTable.FieldLOGS), pcTable.FieldLOGSName || "Расчет вывода таблицы");
                            }
                        });
                        $div.append($('<div><input type="checkbox" name="flds"/> Время расчета полей </div>').append($calcFieldsLogBtn));


                        $div.append('<div style="padding-top: 10px;"><button class="btn btn-sm btn-default">Применить</button></div>');
                        $div.find('input').each(function (i, input) {
                            input = $(input);
                            if (codes.indexOf(input.attr('name')) !== -1) {
                                input.prop('checked', 'checked');
                            }
                        });

                        $div.on('click', 'button', function () {
                            apply()
                        });
                        btn.popover({
                            trigger: "manual",
                            placement: "bottom",
                            content: $div,
                            html: true,
                            animation: false,
                            container: pcTable._container,
                            onhide: function () {

                            }
                        }).popover('show');
                    }
                });

            let img = btn.find('i');
            let codes = $.cookie('pcTableLogs') || '[]';
            codes = JSON.parse(codes);

            if (codes.length > 0) img.addClass('fa-check-square-o'); else img.addClass('fa-square-o');

            let btnLog = $('<button class="btn btn-danger btn-sm">Лог</button>').appendTo(LogButtons);
            pcTable.LogButton = btnLog;
            btnLog.on('click', function () {
                if (!pcTable.FullLOGS || pcTable.FullLOGS.length === 0) {
                    App.logOutput('Лог пуст. Включите логирование и перегрузите страницу');
                } else {
                    App.logOutput(pcTable.FullLOGS);
                }
            });


            if ($TOTUM_FOOTER.length) {
                $TOTUM_FOOTER.append(LogButtons);
            } else {
                LogButtons.appendTo(topButtons);
            }

            $('<button class="btn btn-danger btn-sm"><i class="fa fa-eraser" style="width: 13px"></i></button>')
                .on('click', function () {
                    pcTable.FullLOGS = [];
                    pcTable.LOGS = {};
                }).appendTo(LogButtons);
        }

        let csv = $('<span>').appendTo(topButtons);

        csv.append(this.fieldsHiddingGetButton(true));

        //Печать
        {
            let btn = $('<button class="btn btn-default btn-sm"><i class="fa fa-print"></i></button>')
                .on('click', function () {
                    pcTable._print.call(pcTable)
                }).appendTo(csv);
        }

        if (pcTable.withCsvButtons) {
            let btn = $('<button class="btn btn-default btn-sm">CSV-экспорт</button>')
                .on('click', function () {
                    pcTable._csvExport.call(pcTable)
                });
            csv.append(btn);
        }
        if (pcTable.withCsvEditButtons && this.control.editing) {
            let btn = $('<button class="btn btn-default btn-sm">CSV-импорт</button>')
                .on('click', function () {
                    pcTable._csvImportClick.call(pcTable);
                });
            csv.append(btn);
        }


        if (this.isCreatorView && this.isMain) {
            let creatorPart = $('<div class="creator-buttons">');
            $('<button class="btn btn-default btn-xxs field_name copy_me" data-copied-text="Скопировано"/>').text(this.tableRow.name).appendTo(creatorPart);

            creatorPart.append(' ');

            $('<button class="btn btn-danger btn-xxs" title="Редактировать настройки таблицы"/>').html('<i class="fa fa-pencil-square-o"/>').on('click', function () {
                (new EditPanel(1, BootstrapDialog.TYPE_DANGER, {id: pcTable.tableRow.id})).then(function (json) {
                    if (json) window.location.reload(true);
                });
            }).appendTo(creatorPart);

            creatorPart.append(' ');

            let filters = {'fl_name': [this.tableRow.id]};
            $('<a href="/Table/' + this.Tables.branchId + '/' + this.Tables.id + '/?' + $.param({f: filters}) + '" target="_blank" class="btn btn-danger btn-xxs" title="Открыть список таблиц"/>').html('<i class="fa fa-external-link"/>').appendTo(creatorPart);


            creatorPart.append(' ');

            filters = {'f_table_categories': this.tableRow.category, 'f_table': this.tableRow.id};
            if (this.tableRow.__version) {
                filters.fl_version = this.tableRow.__version
            }
            $('<a href="/Table/' + this.Tables.branchId + '/' + this.TableFields.id + '/?' + $.param({f: filters}) + '" target="_blank" class="btn btn-danger btn-xxs" title="Открыть состав таблиц"/>').html('<i class="fa fa-external-link-square"/>').appendTo(creatorPart);

            creatorPart.append(' ');


            if (this.tableRow.type === "calcs") {
                creatorPart.append(' ');
                let btnCopyTable = $('<a href="/Table/' + this.TablesVersions.branchId + '/' + this.TablesVersions.id + '/?'
                    + $.param({f: this.TablesVersions.version_filters}) + '" target="_blank" class="btn btn-danger btn-xxs" title="Создание версий таблиц"><i class="fa fa-code-fork"></i></a>');
                creatorPart.append(btnCopyTable);

                creatorPart.append(' ');
                btnCopyTable = $('<a href="/Table/' + this.TablesCyclesVersions.branchId + '/' + this.TablesCyclesVersions.id + '/?'
                    + $.param({f: this.TablesCyclesVersions.version_filters}) + '" target="_blank" class="btn btn-danger btn-xxs" title="Изменение версий таблиц цикла"><i class="fa fa-random"></i></a>');
                creatorPart.append(btnCopyTable);


            }
            let type = $('<span class="color-danger" style="padding-left: 8px;">' +
                +this.tableRow.sort + ' <i class="' + App.tableTypes[this.tableRow.type].icon + '"/> '
                + App.tableTypes[this.tableRow.type].title + '</span>');
            creatorPart.append(type);


            if (this.tableRow.type === "calcs") {
                type.append(' / Версия ' + this.tableRow.__version + ' / Цикл ' + this.cycle)
            }

            let btnAdd = $('<button class="btn btn-danger btn-sm" style="margin-left: 8px;">Добавить поле</span></button>').width(113)
                .on('click', function () {
                    let data = {table_id: {v: pcTable.tableRow.id}};
                    if (pcTable.tableRow.__version) {
                        data['version'] = {v: pcTable.tableRow.__version};
                    }
                    (new EditPanel(2, BootstrapDialog.TYPE_DANGER, data)).then(function (json) {
                        if (json) {

                            window.location.reload(true);
                        }
                    })
                }).appendTo(creatorPart);

            creatorPart.appendTo(topButtons);
        }

        this._beforeSpace.append(topButtons);
        this._beforeSpace_title = $('<div class="pcTable-title">').prependTo(this._beforeSpace);
        return this._beforeSpace;
    },
    __$rowsButtons: null,
    _rowsButtons: function () {
        let pcTable = this;

        let buttons;
        if (!this.__$rowsButtons) {
            buttons = $('<div class="pcTable-buttons">');
            this.__$rowsButtons = buttons;
        } else {
            buttons = this.__$rowsButtons.empty();
        }


        if (this.fieldCategories.column.length) {
            if (this.control.adding && !this.f.blockadd) {
                buttons.append(pcTable._getInsertButtons());
            }

            let btnAdd = $('<button class="btn btn-default btn-sm" style="margin-left: 5px;" disabled>Сбросить <span class="fa fa-filter"></span></button>').width(82)
                .on('click', function () {
                    setTimeout(function () {
                        pcTable.filtersEmpty.call(pcTable)
                    }, 50)

                });
            buttons.append(btnAdd);
            this.filtersClearButton = btnAdd;

        }

        return buttons;
    },
    _refreshContentTable: function (forceRecreateRows, forceCheckHeights) {
        let pcTable = this;
        let $content = this._content;
        forceCheckHeights = forceCheckHeights || false;

        $content.data('state', 'refreshing');
        let uin = App.fullScreenProcesses.showCog();


        if (0 && App.isEmpty(this.data)) {
            if ($content.find('.' + this.noDataRowClass).length == 0) {
                $content.append(this._createNoDataRow());
            }
        } else {
            this.ScrollClasterized.insertToDOM(0, forceCheckHeights, forceRecreateRows);
        }
        App.fullScreenProcesses.hideCog();

        $content.data('state', 'ready');
        $content.trigger('refreshed');
        this._popovers.empty();

    },
    _refreshTitle: function () {
        if (window.parent != window) return;
        let pcTable = this;

        this._beforeSpace_title.text(this.tableRow.title);

        if (this.tableRow.description) {
            let btnAdd = $('<span class="btn btn-default btn-sm" style="margin-left: 5px;"><i class="fa fa-info"/></span>');
            let width = 500;
            btnAdd.appendTo(this._beforeSpace_title);
            let offset = btnAdd.get(0).offsetLeft;
            if (offset < 500) {
                width = offset + 150;
            }
            btnAdd.popover(
                {
                    trigger: "manual",
                    placement: "bottom"
                    ,
                    content: $(this.tableRow.description).width(width)
                    ,
                    html: true
                    ,
                    container: this._container
                }
            );

            btnAdd.on('click', function () {
                let btn = $(this);
                if (!btn.data('bs.popover').tip().hasClass('in')) {
                    btn.popover('show');
                    pcTable._container.one('click', function () {
                        btn.popover('hide');
                    });
                    return false;
                }
            });
        }

        if (this.model.tableData && this.model.tableData.updated) {
            let updatedDiv = $('<div class="small">').text(moment(this.model.tableData.updated.dt, 'YYY-MM-DD HH:mm').format('DD.MM HH:mm')).append(' (code: ' + this.model.tableData.updated.code + ')');
            if (this.tableRow["__blocked"]) {
                updatedDiv.append('<div class="">Блокирована'+(this.tableRow["license_error"]?': '+this.tableRow["license_error"]:'')+'</div>');
            }else if (this.control.editing === false) {
                updatedDiv.append('<div class="">Только чтение</div>');
            }


            this._beforeSpace_title.append(updatedDiv);
        }

    },
    _createFiltersBlock: function () {
        this._filtersBlock = $('<div>');

        var pcTable = this;

        if (pcTable.fieldCategories.filter.length) {

            this._filtersBlock.addClass('pcTable-filtersTables');

            let $table, $thead, $tbody;
            let width = 0;
            let ContainerWidth = this._container.width() - 100;
            let panelColor;

            const addGoButtons = function () {
                if ($table) {
                    $thead.append('<th></th>');
                    let $ButtonsGo = $('<td class="buttons-go">').html('<button class="btn btn-default btn-xs button-go">GO</button> <button class="btn btn-default btn-xs eraser button-go"><i class="fa fa-eraser"></i></button>').appendTo($tbody);
                    $table.width(width + 69);
                    $ButtonsGo.on('click', '.button-go', function () {
                        if ($(this).is('.eraser')) {
                            window.location.href = '?';
                        } else {
                            const goFunc = function () {
                                window.location.href = '?' + $.param({'f': pcTable._filtersBlock.data('cryptoFilters') || pcTable.filterDataCrypted});
                            };
                            setTimeout(function () {
                                pcTable.model.doAfterProcesses(goFunc)
                            }, 500);
                        }
                        return true;
                    });
                }
            };
            const addFilterCells = function (k, v) {

                if (v.hidden) return;

                if (width === 0 || (ContainerWidth < (width + v.width)) || v.tableBreakBefore) {
                    addGoButtons();


                    $table = $("<table class='pcTable-filtersTable'><thead><tr></tr></thead><tbody><tr></tr></tbody></table>");

                    pcTable._filtersBlock.append($table);
                    width = 0;
                    $thead = $table.find('thead tr');
                    $tbody = $table.find('tbody tr');
                }

                if (v.panelColor !== undefined) {
                    panelColor = v.panelColor;
                }

                $thead.append(pcTable._createHeadCell(k, v, panelColor));
                $('<td>').attr('data-field', v.name).appendTo($tbody);
                width += v.width;
            };

            $.each(pcTable.fieldCategories.filter, addFilterCells);
            addGoButtons();
        }
        return this._filtersBlock;
    },
    _refreshFiltersBlock: function (newFilters) {
        let pcTable = this;
        newFilters = newFilters || {};

        if (newFilters.params) {
            $.each(pcTable.fieldCategories.filter, function (k, v) {
                pcTable.data_params[v.name] = newFilters.params[v.name];
            });
            pcTable._filtersBlock.data('cryptoFilters', newFilters.filtersString)

        } else if (!pcTable.filterData) {
            pcTable.filterData = {};
            $.each(pcTable.fieldCategories.filter, function (k, v) {
                pcTable.filterData[v.name] = $.extend(true, {}, pcTable.data_params[v.name]);
            });

            pcTable.model.addFiltersData({'filters': pcTable.filterDataCrypted});
        }

        let changed = [];
        let blocked = [];

        $.each(pcTable.fieldCategories.filter, function (k, v) {

            if (v.hidden) return;

            let cell = pcTable._createCell(pcTable.data_params, v);
            /*if (v.insertable === true) {
                v.editable = true;
                cell.addClass('edt');
            }*/
            pcTable._filtersBlock.find('td[data-field="' + v.name + '"]').replaceWith(cell);
            if (!Object.equals(pcTable.data_params[v.name].v, pcTable.filterData[v.name].v)) {
                changed.push(cell);
            }
            if (v.type === 'select' && pcTable.data_params[v.name].v && (pcTable.data_params[v.name].v === '*NONE*' || pcTable.data_params[v.name].v[0] === '*NONE*')) {
                blocked.push(cell);
            }
        });

        if (changed.length > 0) {
            changed.forEach(function ($td) {
                $td.addClass('warning-backg');
            });
            pcTable._filtersBlock.removeClass('with_danger').addClass('with_changed')
        } else if (blocked.length > 0) {
            blocked.forEach(function ($td) {
                $td.addClass('danger-backg');
            });
            pcTable._filtersBlock.removeClass('with_changed').addClass('with_danger')
        } else {
            pcTable._filtersBlock.removeClass('with_danger, with_changed');
            pcTable._filtersBlock.find('.danger-backg, .warning-backg').removeClass('danger-backg, warning-backg');
        }
        return this._filtersBlock;
    },
    _createParamsBlock: function () {
        let $paramsBlock = $('<div>');

        let pcTable = this;
        if (pcTable.fieldCategories.param) {
            $paramsBlock.addClass('pcTable-paramsTables');

            let $table, $thead, $tbody;
            let width = 0;
            let ContainerWidth = this._container.width() - 100;
            let panelColor;
            $.each(pcTable.fieldCategories.param, function (k, field) {

                if (!field.showMeWidth) return;

                if (width === 0 || (ContainerWidth < (width + field.showMeWidth)) || field.tableBreakBefore) {
                    if ($table) $table.width(width);

                    $table = $("<table class='pcTable-paramsTable'><thead><tr></tr></thead><tbody><tr></tr></tbody></table>");
                    $paramsBlock.append($table);

                    width = 0;
                    $thead = $table.find('thead tr');
                    $tbody = $table.find('tbody tr');
                }
                if (field.panelColor !== undefined) {
                    panelColor = field.panelColor;
                }
                $thead.append(pcTable._createHeadCell(k, field, panelColor));
                $tbody.append('<td data-field="' + field.name + '">');
                width += field.showMeWidth;
            });
            if ($table) {
                $table.width(width);

            }

        }
        return $paramsBlock;
    },
    _refreshParamsBlock: function (paramsChanges, colorizeIt) {
        var pcTable = this;
        if (pcTable.fieldCategories.param) {

            $.each(pcTable.fieldCategories.param, function (k, v) {
                if (!v.showMeWidth || paramsChanges && paramsChanges[v.name] !== true) return true;

                let cell = pcTable._createCell(pcTable.data_params, v);
                pcTable._paramsBlock.find('td[data-field="' + v.name + '"]').replaceWith(cell);
                if (colorizeIt) {
                    pcTable._colorizeElement(cell, pcTable_COLORS.saved);
                }
            })
        }
        return this._paramsBlock;
    },
    _createFootersBlock: function () {
        let pcTable = this;
        let _footersBlock;
        let _footersSubTable;
        if (pcTable.fieldCategories['footer']) {

            _footersBlock = $("<tbody class='pcTable-footers'></tbody>");
            _footersSubTable = $("<div>");

            pcTable.columnsFooters = {};
            var maxInColumn = 0;

            $.each(pcTable.fieldCategories['footer'], function (k, field) {
                if (field.showMeWidth) {
                    if (!field.column) field.column = '';

                    if (!pcTable.columnsFooters[field.column]) pcTable.columnsFooters[field.column] = [];
                    pcTable.columnsFooters[field.column].push(field);
                    if (field.column) {
                        if (maxInColumn < pcTable.columnsFooters[field.column].length) maxInColumn = pcTable.columnsFooters[field.column].length;
                    }
                }
            });

            var NewFooters = $();
            var footerVarNum = 0;
            var footerLastMaxNum = maxInColumn - 1;
            var trIndex = 0;
            while (footerVarNum < maxInColumn) {


                var trHead = $('<tr><td class="id"></td></tr>');

                var trVal = $('<tr><td class="id"></td></tr>').data(pcTable_ROW_ItemId_KEY, 'footers').data(pcTable_DATA_INDEX, 'footers' + (trIndex++));

                $.each(pcTable.fieldCategories['column'], function (k, field) {
                    if (field.showMeWidth) {
                        let td = $('<td>');
                        if (!pcTable.columnsFooters[field.name] || !pcTable.columnsFooters[field.name][footerVarNum]) {
                            td.attr('rowspan', 2);
                            trHead.append(td);
                            td.addClass('footer-empty');
                            return;

                        } else {
                            td = pcTable._createHeadCell(k, pcTable.columnsFooters[field.name][footerVarNum], pcTable.columnsFooters[field.name][footerVarNum].panelColor).addClass('footer-name');
                            trHead.append(td);
                        }

                        if (pcTable.columnsFooters[field.name] && pcTable.columnsFooters[field.name][footerVarNum]) {

                            let footerField = pcTable.columnsFooters[field.name][footerVarNum];
                            let td = pcTable._createCell(pcTable.data_params, footerField);
                            td.attr('data-field', footerField.name);
                            trVal.append(td);
                        }
                    }

                });


                NewFooters = NewFooters.add(trHead);
                NewFooters = NewFooters.add(trVal);

                footerVarNum++;
            }
            _footersBlock.html(NewFooters);

            let width = 0, $table, $thead, $tbody;
            let ContainerWidth = this._container.width() - 100;
            let panelColor;
            $.each(pcTable.columnsFooters[''], function (k, field) {
                if (field.showMeWidth) {
                    if (width === 0 || (ContainerWidth < (width + field.showMeWidth)) || field.tableBreakBefore) {
                        if ($table) $table.width(width);

                        $table = $("<table class='pcTable-footers-table'><thead><tr></tr></thead><tbody><tr></tr></tbody></table>\"");
                        _footersSubTable.append($table);
                        width = 0;
                        $thead = $table.find('thead tr');
                        $tbody = $table.find('tbody tr');
                    }
                    if (field.panelColor !== undefined) {
                        panelColor = field.panelColor;
                    }
                    $thead.append(pcTable._createHeadCell(k, field, panelColor));
                    $tbody.append('<td data-field="' + field.name + '">');
                    width += field.showMeWidth;
                }
            });
            if ($table) $table.width(width);

        }
        return [_footersBlock, _footersSubTable];
    },
    _refreshFootersBlock: function (paramsChanges, colorizeIt) {
        let pcTable = this;
        let footers = pcTable._footersBlock.add(pcTable._footersSubTable)

        if (pcTable.fieldCategories.footer) {
            $.each(pcTable.fieldCategories.footer, function (k, v) {
                if (!v.showMeWidth || paramsChanges && paramsChanges[v.name] !== true) return true;

                let cell = pcTable._createCell(pcTable.data_params, v);
                cell.attr('data-field', v.name);
                footers.find('td[data-field="' + v.name + '"]').replaceWith(cell);

                if (colorizeIt) {
                    pcTable._colorizeElement(cell, pcTable_COLORS.saved);
                }
            })
        }
        return this._paramsBlock;


    },
    _createHead: function () {
        this._header = $("<thead>").append(this._createHeadRow());
        return this._header;
    },
    _refreshHead: function () {
        this._header.empty().append(this._createHeadRow());
        return this._header;
    },
    _createFirstBody: function () {
        this._beforebody = $("<tbody class='beforeRows'>").append('<tr class="extra-clasters">');
        this.extraClastersTop = this._beforebody.find('.extra-clasters');
        return this._beforebody;
    },
    _createAfterBody: function () {
        this._afterbody = $("<tbody class='afterRows'>").append('<tr class="extra-clasters">');
        this.extraClastersBottom = this._afterbody.find('.extra-clasters');
        return this._afterbody;
    },
    _createBody: function () {
        this._content = $("<tbody class='dataRows'>");

        return this._content;
    },
    _createHeadRow: function () {
        let pcTable = this;
        let $row = $("<tr>");
        let $width = 60;

        pcTable._createHeadCellId().appendTo($row);
        let panelColor;
        $.each(this.fieldCategories.visibleColumns, function (index, field) {
            if (field.panelColor !== undefined) {
                panelColor = field.panelColor;
            }
            let $th;
            if (field.name === 'n') {
                $th = pcTable._createHeadCellNo();
            } else {
                $th = pcTable._createHeadCell(index, field, panelColor);
            }
            $th.appendTo($row);
            $width += parseInt(field.showMeWidth);
        });

        this.tableWidth = $width;

        this._table.width(this.tableWidth);

        return $row;
    },
    _createHeadCellId: function () {
        let pcTable = this;
        let $th = $('<th class="id"><span>id</span></th>');

        if (pcTable.tableRow.order_field === null || pcTable.tableRow.order_field === 'id') {
            let span = $th.find('span').css('font-weight', 'bold');
            if (pcTable.isCreatorView) {
                if (pcTable.tableRow.order_desc === true) {
                    span.append(' <i class="fa fa-sort-amount-desc roles"></i>');
                } else {
                    span.append(' <i class="fa fa-sort-amount-asc roles"></i>');
                }
            }
        }

        let panel = $('<div class="pcTable-filters"></div>');


        /*******Кнопка показать поле n*****/
        let OrderClass = 'btn-warning';

        let $btnNHiding = $('<button class="btn btn-default btn-xxs"><i class="fa fa-sort"></i></button>')
            .on('click', function () {
                if (!pcTable.fieldCategories.visibleColumns.some(function (field) {
                    return field.name === 'n';
                })) {
                    pcTable.fieldsHiddingHide.call(pcTable, 'n', true);
                    $btnNHiding.addClass(OrderClass)
                } else {
                    pcTable.fieldsHiddingHide.call(pcTable, 'n');
                    $btnNHiding.removeClass(OrderClass)
                }
            });
        if (pcTable.fieldCategories.visibleColumns.some(function (field) {
            return field.name === 'n';
        })) {
            $btnNHiding.addClass(OrderClass)
        }
        /*******Кнопка показать поле n*****/


        let filterButton = this._getIdFilterButton();

        panel.append($btnNHiding)
            .append(' ')
            .append(filterButton)
            .append(' ')
            .append(pcTable._idCheckButton);

        $th.append(this._checkStatusBar);
        $th.append(panel);

        pcTable._idCheckButton.off().on('click', function () {
            if (pcTable._idCheckButton.find('span').is('.fa-check')) {
                pcTable.row_actions_uncheck_all.call(pcTable);
                pcTable.__checkedRows = [];
            } else {
                for (let i = 0; i < pcTable.dataSortedVisible.length; i++) {
                    let id = pcTable.dataSortedVisible[i];
                    let item = pcTable._getItemById(id);
                    pcTable.row_actions_check.call(pcTable, item, true);
                }
                pcTable.__checkedRows = pcTable.dataSortedVisible.slice();
            }
            pcTable._headCellIdButtonsState();
        });

        panel = $('<div class="pcTable-filters for-selected"><button class="btn btn-default btn-xxs"><i class="fa fa-copy"/></button> <button class="btn btn-default btn-xxs" data-names="true"><i class="fa fa-clone"/></button></div>');
        $th.append(panel);

        this._refreshCheckedStatus();

        return $th;
    },
    _getIdFilterButton: function () {
        let pcTable = this;
        let filterButton;
        let span = $('<span>');

        filterButton = $('<button class="btn btn-xxs btn-filter" id="checkS"><span class="fa fa-circle-o"></span></button>').on('click', function () {
            if (filterButton.is('.btn-warning')) {
                filterButton.addClass('btn-default').removeClass('btn-warning').find('span').removeClass('fa-circle').addClass('fa-circle-o');

                delete pcTable.filters['id'];
                span.find('.btn-filter:not(#checkS)').parent().replaceWith(pcTable.__getFilterButton('id'))
            } else {
                filterButton.removeClass('btn-default').addClass('btn-warning').find('span').removeClass('fa-circle-o').addClass('fa-circle');
                pcTable.filters['id'] = pcTable.__checkedRows.slice().map(function (v) {
                    return v.toString()
                });

            }
            pcTable.__applyFilters();
            pcTable._headCellIdButtonsState();
        });
        if (pcTable.filters.id && pcTable.filters.id.length) {
            filterButton.addClass('btn-warning').removeClass('btn-default');
        } else {
            filterButton.addClass('btn-default').removeClass('btn-warning');
        }

        span.append(filterButton);
        span.append(pcTable.__getFilterButton('id'));

        return span;
    },
    _createHeadCellNo: function () {
        let pcTable = this;
        let field = pcTable.fields['n'];
        let spanTitle = $('<span class="cell-title">')
            .text(field.title ? field.title : field.name)
            .attr('title', field.title);
        let btn = $('<button class="btn btn-default btn-xxs" style="width: 45px"><i class="fa fa-save"></i></button>');

        let $th = $('<th class="n">').width(field.userWidth || field.width).append(spanTitle);

        if (pcTable.isCreatorView) {
            if (pcTable.tableRow.order_field === 'n') {
                if (pcTable.tableRow.order_desc === true) {
                    spanTitle.before('<i class="fa fa-sort-amount-desc roles"></i>');
                } else {
                    spanTitle.before('<i class="fa fa-sort-amount-asc roles"></i>');
                }
            }
            spanTitle.before('<br/>');
        }


        pcTable._orderSaveBtn = btn;
        if (pcTable.tableRow.with_order_field && !pcTable.f.blockorder && !pcTable.tableRow['__blocked']) {
            btn.on('click', function () {
                pcTable.reOrderRowsSave.call(pcTable);
            })
        } else {
            btn.prop('disabled', true);
            this._table.addClass('no-correct-n-filtered');
        }
        return $th.append($('<div class="pcTable-filters">').append(btn));
    }
    ,
    _createHeadCell: function (index, field, panelColor) {
        let pcTable = this;

        let width = field.showMeWidth || field.width || 100;

        let $th = $('<th>')
            .data('field', field.name)
            .width(width);

        if (pcTable.fields[field.name]) {
            pcTable.fields[field.name].$th = $th;
        }

        if (panelColor !== undefined && panelColor !== '') {
            $th.css('background-color', panelColor);
        }
        if (field.webRoles && field.webRoles.length === 1 && field.webRoles[0] === "1") {
            $th.addClass('admin-see');
        }

        if (field['type'] === 'footer' && field['column']) {
            $th = $('<td>');
        }

        let spanTitle = $('<span class="cell-title">')
            .text(field.title ? field.title : field.name)
            .attr('title', field.title ? field.title : field.name).appendTo($th);

        if (pcTable.isCreatorView) {

            if (field.isHiddenField || !field.showMeWidth) {
                let placeIcon = '';
                switch (field.category) {
                    case 'param':
                        placeIcon = 'fa-hand-o-up';
                        break;
                    case 'column':
                        placeIcon = 'fa-hand-o-right';
                        break;
                    case 'filter':
                        placeIcon = 'fa-hand-o-left';
                        break;
                    case 'footer':
                        placeIcon = 'fa-hand-o-down';
                        break;
                }
                spanTitle.before('<i class="fa ' + placeIcon + ' roles"></i>');
            }

            if (field.showInWeb && !field.isHiddenField && !field.showMeWidth) {
                $th.addClass('eye-hidden');
            }

            if (field.linkTableName) {
                spanTitle.before('<i class="fa fa-chain roles"></i>');
            }
            spanTitle.before('<i class="fa ' + field.icon + ' roles"></i>');
            let $ord = $('<i class="roles">' + field.ord + '</i>');
            spanTitle.before($ord);


            if (field.category === 'column' && pcTable.tableRow.order_field === field.name) {
                $ord.css('font-weight', 'bold');
                if (pcTable.tableRow.order_desc === true) {
                    spanTitle.before('<i class="fa fa-sort-amount-desc roles"></i>');
                } else {
                    spanTitle.before('<i class="fa fa-sort-amount-asc roles"></i>');
                }
            }

            if (field.codeAction) {
                let star = $('<i class="fa fa-star roles"></i>');
                spanTitle.before(star);
                let title = '';
                if (field.CodeActionOnAdd) {
                    if (title !== '') title += "\n";
                    title += 'При добавлении';
                }
                if (field.CodeActionOnChange) {
                    if (title !== '') title += "\n";
                    title += 'При изменении';
                }
                if (field.CodeActionOnDelete) {
                    if (title !== '') title += "\n";
                    title += 'При удалении';
                }
                if (field.type === 'button') {
                    if (title !== '') title += "\n";
                    title += 'При клике';
                }
                if (title === "") {
                    star.removeClass('fa-star').addClass('fa-star-o');
                }
                star.attr('title', title);
            }
            if (field.code) {
                if (field.codeOnlyInAdd) {
                    spanTitle.before('<i class="fa fa-cog-o roles"></i>');
                } else {
                    spanTitle.before('<i class="fa fa-cogs roles"></i>');
                }
            }

            const getRoles = function (rolesList) {
                let roles = '';
                rolesList.forEach(function (r) {
                    if (roles !== '') roles += "\n";
                    roles += pcTable.ROLESLIST[r.toString()]
                });
                return roles;
            };

            if (field.webRoles) {
                spanTitle.before($('<i class="fa fa-eye roles"></i>').attr('title', getRoles(field.webRoles)));
            }
            if (field.type !== "button") {
                let lockInHead = false;
                if (field.addRoles) {
                    spanTitle.before($('<i class="fa fa-plus roles"></i>').attr('title', getRoles(field.addRoles)));
                } else if (!field.insertable) {
                    if (field.category === "column") {
                        if (!field.editable) {
                            spanTitle.before($('<i class="fa fa-lock roles"></i>').attr('title', 'Добавление и редактирование запрещено'));
                            lockInHead = true;
                        } else {
                            spanTitle.before($('<i class="fa fa-plus roles"></i>').attr('title', 'Добавление запрещено'));
                        }
                    } else if (!field.editable) {
                        spanTitle.before($('<i class="fa fa-lock roles"></i>').attr('title', 'Редактирование запрещено'));
                        lockInHead = true;
                    }
                }

                if (field.editRoles) {
                    spanTitle.before($('<i class="fa fa-pencil roles"></i>').attr('title', getRoles(field.editRoles)));
                } else if (!field.editable && !lockInHead) {
                    spanTitle.before($('<i class="fa fa-pencil roles"></i>').attr('title', 'Редактирование запрещено'));
                }

                if (field.logRoles) {
                    spanTitle.before($('<i class="fa fa-archive roles"></i>').attr('title', getRoles(field.logRoles)));
                }
            }
            if (field.showInXml) {
                let roles = '';
                if (field.xmlRoles) {
                    roles = getRoles(field.xmlRoles)
                }
                spanTitle.before($('<i class="fa fa-exchange roles"></i>').attr('title', roles));
            }


            spanTitle.before('<br/>');
        }

        if (field.unitType) {
            spanTitle.append(', ' + field.unitType);
        }


        let filterBlock = $('<div class="pcTable-filters">');

        //i
        let span_help;
        if (field.help && field.help.length && pcTable.isCreatorView) {
            span_help = $('<span class="btn btn-default field_name btn-xxs" tabindex="-1"><i class="fa fa-info"/></span>');
            span_help.appendTo(filterBlock);
            let i_content = $('<div class="i-inner-div">').html(field.help).width(230);
            span_help.on('click', function () {
                let btn = $(this);


                if (!btn.data('bs.popover')) {
                    let placement = 'bottom';
                    let height = 300;
                    let container = pcTable._container;
                    let heightOffset = btn.offset().top - container.offset().top;

                    //Определить где больше места - сверху или снизу
                    if (heightOffset > container.height() / 2) {
                        placement = 'top';
                        height = heightOffset - 40;
                    } else {
                        height = container.height() - heightOffset - 70;
                    }
                    i_content.css('max-height', height);

                    span_help.popover(
                        {
                            trigger: "manual",
                            content: i_content,
                            html: true,
                            placement: placement,
                            container: pcTable.scrollWrapper
                        }
                    );
                    btn.popover('show');
                    setTimeout(function () {
                        let eventsName = '.' + field.name + '-i';

                        pcTable._container.on('click' + eventsName + ' escPressed' + eventsName, function () {
                            if (btn.data('bs.popover'))
                                btn.popover('destroy');
                            pcTable._container.off(eventsName);
                        });
                    }, 20);
                }
            });
        }


        if (field.category === 'column') {

            if (field.filterable && field.showMeWidth>0) {
                this.__getFilterButton(field.name).appendTo(filterBlock);
            }
        }

        //Стрелочка с выпадающим меню
        if (pcTable.isCreatorView || (field.dropdownView !== false && (field.linkToSelectTable || field.category !== 'filter'))) {
            $th.addClass('with-filter');

            (function () {
                let $divPopoverArrowDown = $('<div>');
                let btnDropDown = $('<button class="btn btn-default btn-xxs"  tabindex="-1">' +
                    '<i class="fa fa-caret-down"/></button>');

                if(!field.pcTable){
                    btnDropDown.addClass('field_name')
                }
                else if (field.dropdownView === false) {
                    btnDropDown.addClass('field_name')
                }

                const funcOnTableChanged = function (json) {
                    if (json) {

                        window.location.reload();
                    }
                };

                if (pcTable.isCreatorView) {
                    let btn = $('<div class="menu-item color-danger">');
                    btn.append('<i class="fa fa-pencil-square-o"/> Изменить');
                    $divPopoverArrowDown.append(btn);
                    const contextmenu = function () {
                        (new EditPanel(2, BootstrapDialog.TYPE_DANGER, {id: field.id})).then(funcOnTableChanged);
                        return false;
                    };

                    btn.on('click', contextmenu);
                    btnDropDown.on('contextmenu', contextmenu);


                    btn = $('<div class="menu-item color-danger">');
                    btn.append('<i class="fa fa-clone"/> Дублировать');
                    $divPopoverArrowDown.append(btn);
                    btn.on('click', function () {

                        App.getPcTableById(2).then(function (pcTableTablesFields) {
                            pcTableTablesFields.model.checkEditRow({id: field.id}).then(function (json) {
                                let ee = {};
                                $.each(json.row, function (k, v) {
                                    if (typeof v === 'object') {
                                        ee[k] = v;
                                    }
                                });

                                (new EditPanel(2, BootstrapDialog.TYPE_DANGER, ee)).then(funcOnTableChanged);
                            });
                        })


                    });

                    btn = $('<div class="menu-item color-danger">');
                    btn.append('<i class="fa fa-plus"/> Вставить после');
                    $divPopoverArrowDown.append(btn);
                    btn.on('click', function () {
                        App.getPcTableById(2, {afterField: field.ord}).then(function (pcTableTablesFields) {
                            let ee = {};
                            ee.ord = {'v': field.ord + 10};
                            ee.category = {v: field.category};
                            ee.table_id = {v: pcTable.tableRow.id};
                            if (pcTable.tableRow.__version) {
                                ee.version = {v: pcTable.tableRow.__version};
                            }
                            (new EditPanel(2, BootstrapDialog.TYPE_DANGER, ee)).then(funcOnTableChanged);
                        })
                    });
                    btn = $('<div class="menu-item color-danger">');
                    btn.append('<i class="fa fa-refresh"/> Изменить NAME');
                    $divPopoverArrowDown.append(btn);
                    btn.on('click', function () {
                        pcTable.model.renameField(field.name);
                    });


                    btn = $('<div class="menu-item color-danger">');
                    btn.append('<i class="fa fa-times"/> Удалить');
                    $divPopoverArrowDown.append(btn);
                    btn.on('click', function () {


                        let title = field.title;

                        BootstrapDialog.show({
                            type: BootstrapDialog.TYPE_DANGER,
                            title: 'Удалить поле ' + title + ' из таблицы ' + pcTable.tableRow.title + '?',
                            buttons: [
                                {
                                    action: function (panel, event) {
                                        "use strict";
                                        panel.close();
                                        App.getPcTableById(2).then(function (pcTableFields) {
                                            App.panelTimer('Удаление поля ' + title + ' из таблицы ' + pcTable.tableRow.title, pcTableFields.tableRow.delete_timer, function () {
                                                pcTableFields.model.delete(field.id).then(function () {
                                                    window.location.reload(true);
                                                })
                                            });
                                        });
                                    },
                                    cssClass: 'btn-warning',
                                    label: 'Удалить'
                                },
                                {
                                    action: function (panel) {
                                        panel.close();
                                    },
                                    label: 'Отмена'
                                }
                            ],
                            draggable: true
                        })
                    });

                }

                if (field.category !== 'filter' && field.pcTable) {

                    //Скрыть
                    let btn = $('<div class="menu-item">');
                    btn.append('<i class="fa fa-eye-slash"/> Скрыть');
                    btn.on('click', function () {
                        btnDropDown.popover('hide');
                        pcTable.fieldsHiddingHide.call(pcTable, field.name);
                    });
                    btn.appendTo($divPopoverArrowDown);

                    //ширина
                    btn = $('<div class="menu-item">');
                    btn.append('<i class="fa fa-arrows-h"/> Ширина поля');
                    btn.on('click', function () {
                        btnDropDown.popover('hide');
                        let div = $('<div><input type="number" class="form-control" value="' + field.showMeWidth + '" style="padding-left: 2px;"/></div>');
                        BootstrapDialog.show({
                            message: div,
                            cssClass: 'edit-row-panel',
                            title: 'Ширина поля ' + field.title,
                            onshown: function (dialog) {
                                let inp = div.find('input');
                                inp.focus();
                                inp.on('keydown', function (event) {
                                    if (event.keyCode === 13) {
                                        field.pcTable.setColumnWidth.call(field.pcTable, field.name, parseInt(div.find('input').val()));
                                        dialog.close()
                                    }
                                });
                                dialog.$modalDialog.width(500);
                            },
                            buttons: [
                                {
                                    label: 'Применить',
                                    action: function (dialog) {
                                        let width = parseInt(div.find('input').val());
                                        dialog.close();
                                        field.pcTable.setColumnWidth.call(field.pcTable, field.name, width);

                                    }
                                },
                                {
                                    label: 'Отмена',
                                    action: function (dialog) {
                                        dialog.close()
                                    }
                                }
                            ],
                            draggable: true
                        })

                    });
                    btn.appendTo($divPopoverArrowDown);

                }
                if (field.showMeWidth>0 && field.category === 'column') {
                    //sort a-z
                    {
                        let btn = $('<div class="menu-item">');
                        btn.append('<i class="fa fa-sort-alpha-asc"/> Сортировать А-Я');
                        $divPopoverArrowDown.append(btn)
                        btn.on('click', function () {
                            pcTable.sort(field, 1);
                        })
                    }
                    //sort z-a
                    {
                        let btn = $('<div class="menu-item">');
                        btn.append('<i class="fa fa-sort-alpha-desc"/> Сортировать Я-А');
                        $divPopoverArrowDown.append(btn);
                        btn.on('click', function () {
                            pcTable.sort(field, -1);
                        })
                    }
                    //Математические операции
                    if (field.category === 'column' && field.type === 'number') {
                        let btn = $('<div class="menu-item">');
                        btn.append('<i class="fa fa-diamond"/> Математические операции');
                        $divPopoverArrowDown.append(btn);
                        btn.on('click', function () {
                            let $div = $('<div>');

                            let summ = 0, count = 0, max = null, min = null, notNumber = 0;
                            let error = false;
                            pcTable.dataSortedVisible.some(function (id) {
                                try {
                                    let BigVal = Big(pcTable.data[id][field.name].v);

                                    summ = Big(summ).plus(BigVal);
                                    ++count;
                                    if (max === null) max = BigVal;
                                    else {
                                        if (BigVal.gt(max)) max = BigVal;
                                    }
                                    if (min === null) min = BigVal;
                                    else {
                                        if (BigVal.lt(min)) min = BigVal;
                                    }
                                } catch (e) {
                                    ++notNumber;
                                }
                            });


                            let table = $('<table><thead><tr><th style="width: 180px;height: 32px;">Операция</th><th style="width: 200px;">Значение</th></tr></thead>').appendTo($div),
                                tbody = $('<tbody>').appendTo(table);

                            let format = function (num, notUnit) {
                                let unit = '';
                                notUnit = notUnit || false;
                                if (field.unitType && !notUnit) {
                                    unit = ' ' + field.unitType;
                                }
                                if (field.currency) {
                                    let options = {};
                                    if (field.dectimalPlaces) {
                                        options.minimumFractionDigits = field.dectimalPlaces;
                                    }
                                    return parseFloat(num).toLocaleString('ru-RU', options) + unit;
                                }
                                return num + unit;
                            };

                            $('<tr><td>Сумма</td><td>' + format(summ) + '</td></tr>').appendTo(tbody);
                            $('<tr><td>Кол-во чисел</td><td>' + format(count, true) + '</td></tr>').appendTo(tbody);
                            $('<tr><td>Среднее</td><td>' + format(Big(summ).div(count).round(field.dectimalPlaces || 0)) + '</td></tr>').appendTo(tbody);
                            $('<tr><td>Максимальное</td><td>' + format(max) + '</td></tr>').appendTo(tbody);
                            $('<tr><td>Минимальное</td><td>' + format(min) + '</td></tr>').appendTo(tbody);
                            $('<tr><td>Нечисл. элементов</td><td>' + format(notNumber, true) + '</td></tr>').appendTo(tbody);


                            BootstrapDialog.show({
                                title: field.title + (field.unitType ? ', ' + field.unitType : ''),
                                type: 'edit',
                                message: $div,
                                draggable: true,
                                onshown: function (dialog) {
                                    dialog.$modalDialog.width(400);
                                },
                                buttons: [
                                    {
                                        action: function (dialog) {
                                            dialog.close();
                                        },
                                        label: 'Закрыть'
                                    }
                                ]
                            });
                        })
                    }

                }

                //linkToSelectTable
                if (field.linkToSelectTable) {
                    let table = field.linkToSelectTable;
                    let btn = $('<div class="menu-item color-primary">');
                    btn.append('<i class="fa fa-external-link"/> ' + table['title']);
                    $divPopoverArrowDown.append(btn);
                    btn.on('click', function () {
                        window.open(table.link, '_blank').focus()
                        return false;
                    })
                }


                btnDropDown.popover({
                    html: true,
                    content: $divPopoverArrowDown,
                    trigger: 'manual',
                    container: pcTable._container,
                    placement: 'auto bottom'
                });

                btnDropDown.on('click', function () {
                    let btn = $(this);

                    if (!btn.data('bs.popover').tip().hasClass('in')) {
                        btn.popover('show');
                        setTimeout(function () {
                            pcTable._container.one('click', function () {
                                btn.popover('hide');
                            });
                        }, 20);
                    }
                });
                return btnDropDown;

            })().appendTo(filterBlock);
        }

        filterBlock.appendTo($th);

        if (this.isCreatorView) {
            let pcTableCreatorButtonsBlock = $('<div class="th-left-bottom-buttons">').appendTo($th);

            let btn = $('<div class="btn  btn-xxs field_name copy_me"  tabindex="-1" data-copied-text="Скопировано">')
                .text(field.name).appendTo(pcTableCreatorButtonsBlock);

            let wdthMinus = 34;
            if (field.filterable) {
                wdthMinus += 15;
            }
            if (span_help) {
                wdthMinus += 14;
            }
            if (field.filterable || span_help) {
                wdthMinus += 5;
                if (field.filterable && span_help) {
                    span_help.css('margin-right', -3);
                    wdthMinus += 2;
                }
            }


            if (field.category === 'footer' && field.column && this.fields[field.column] && !pcTable.hidden_fields[field.name]) {
                width = this.fields[field.column].width;
            }


            let maxWidthBtn = width - wdthMinus;
            let correctBtnWidth = function () {
                let wdth = btn.width();
                if (wdth == 0) {
                    setTimeout(correctBtnWidth, 30);
                    return false;
                }
                wdth += 10;
                if (wdth > maxWidthBtn) {
                    btn.width(maxWidthBtn);
                }
            };
            correctBtnWidth();
        }

        return $th;
    },
    _createNoDataRow: function (text) {
        var amountOfFields = 0;
        $.each(this.fields, function (index, field) {
            amountOfFields++;
        });
        let pcTable = this;

        let $addBtn = $();
        if (this.control.adding && !this.f.blockadd) {
            $addBtn = $('<button class="btn btn-warning btn-xxs">Добавить строку</button>').width(120)
                .on('click', function () {
                    pcTable._addInsert.call(pcTable)
                });
        }

        text = text || 'Таблица пуста ';

        return $("<tr>").addClass(this.noDataRowClass)
            .append('<td class="id">')
            .append($("<td>").attr("colspan", amountOfFields).append(text).append($addBtn));
    },
    _createRow: function (item, chData) {
        chData = chData || [];
        let pcTable = this;

        if (!item.$tr) {
            item.$tr = $("<tr>");
            item.$tr.height(pcTABLE_ROW_HEIGHT);
            /* перенос в css сглючивает прокрутку*/
            item.$tr.data('item', item);
        }

        let $row = item.$tr.empty();
        $row.attr('class', 'DataRow');
        $row.attr('data-' + pcTable_ROW_ItemId_KEY, item['id']);

        if (item.e_data && item.e_data.b == true) {
            $row.addClass('BlockedRow');
        }

        if (item['InsDel']) {
            $row.addClass('insDeleted');
        }
        this._addCellId(item, $row);
        // this._addCellNo(item, $row);

        let i = 0;
        let len = this.fieldCategories.visibleColumns.length;
        if (this.fieldCategories.visibleColumns[i] && this.fieldCategories.visibleColumns[i].name === 'n') {
            let field = this.fieldCategories.visibleColumns[i];
            let td = $('<td>');
            $row.append(td.append('<span class="cell-value">').append(field.getCellText(null, td, item)));
            ++i;
        }

        for (i; i < len; ++i) {
            let field = this.fieldCategories.visibleColumns[i];
            let td;

            $row.append(td = pcTable._createCell(item, field));
            if (chData.indexOf(field.name) > -1) {
                pcTable._colorizeElement(td, pcTable_COLORS.saved);
            }
        }

    },
    refreshRow: function (tr, item, newData) {
        if ((tr && tr.is('.DataRow')) || item) {

            if (!item) {
                item = this._getItemByTr(tr);
            }

            let chData = [];
            if (newData) {
                for (var k in newData) {
                    if (newData[k] !== null && typeof newData[k] == 'object') {
                        if (newData[k].changed) {
                            chData.push(k);
                            delete newData[k].changed;
                        } else if (!Object.equals(newData[k], item[k])) {
                            chData.push(k);
                        }
                    } else if (newData[k] != item[k]) {
                        chData.push(k);
                    }
                    item[k] = newData[k];
                }

                $.extend(item, newData);
            }

            if (tr) this._createRow(item, chData);
        } else if (this._isParamsArea(tr)) {
            this._refreshParamsBlock();
        } else if (this._isFootersArea(tr)) {
            this._refreshFootersBlock();
        }

    },

    _createCell: function (item, field) {
        let pcTable = this;
        var td = $("<td>");
        let format;

        if (!item[field.name]) {
            console.log('Не найдено поле ' + field.name);
            console.log(item);
        }

        try {
            format = $.extend({}, (pcTable.f || {}), (item.f || {}), (item[field.name].f || {}));
        } catch (e) {
            console.log('test');
            console.log(e, item, field.name);
            format = {};
        }


        if (field.editable && (this.control.editing || field.category === 'filter') && !format.block) {
            td.addClass('edt');
            if (field.editGroup) {

                if (field.editGroupMultiColumns) {
                    td.addClass('e-gm').addClass('e-g');
                } else {
                    td.addClass('e-g');
                }
            }
        }


        if (format.block) {
            td.addClass('blocked');
        }

        if (field.category !== 'column') {
            td.attr('data-field', field.name);
        }

        var span = $('<span class="cell-value">')
        var val = item[field.name];

        if (field.code && !field.codeOnlyInAdd) {
            td.addClass('with-code');
        }

        if (field.category !== 'column') {
            td.data('field', field.name).addClass('val')
        }
        let isErrorVal;
        if (val) {

            isErrorVal = val.e;

            if (val.h) {
                let $hand;
                if (val.c !== undefined && val.v != val.c) {
                    $hand = $('<i class="fa fa-hand-paper-o pull-right" aria-hidden="true"></i>');
                } else {
                    $hand = $('<i class="fa fa-hand-rock-o pull-right" aria-hidden="true"></i>');
                }
                td.append($hand);
            }
            if (val.d) {
                td.addClass('deleted_value');
            }


            if (val.e) {
                if (field.errorText) {
                    span.text(field.errorText);
                } else {
                    let e = $('<i class="fa fa-exclamation-triangle pull-right" aria-hidden="true"></i>').attr('title', val.e);
                    td.append(e);
                }
            }

            if (format.text && field.type != "button") {
                span.text(format.text);
            } else if (!val.e || !field.errorText) {
                var cellInner = field.getCellText.call(field, val.v, td, item, pcTable);
                if (typeof cellInner === 'object') {
                    span.html(cellInner)
                } else span.text(cellInner);
            }
        }

        span.appendTo(td);


        if (!format.text && field.unitType && !isErrorVal && val.v !== null) {
            span.attr('data-unit-type', ' ' + field.unitType);
        }

        //td.width(field.width);

        if (field.css) {
            td.addClass(field.css)
        }
        if (this.isSelected(field.name, item.id)) {
            td.addClass('selected');
        }
        if (format.background) {
            td.css('background-color', format.background);
        }


        if (format.color) td.css('color', format.color);
        if (format.bold) td.css('font-weight', 'bold');

        if (format.align) {
            td.css('text-align', format.align);
        } else if (format.tab) {
            td.css('padding-left', format.tab + "px");
        }

        if (format.decoration) td.css('text-decoration', format.decoration);
        if (format.italic) td.css('font-style', 'italic');

        if (field.type !== "button") {
            if (format.comment) {
                let i;
                i = $('<i class="cell-icon fa fa-info"></i>');
                td.prepend(i);
                i.attr('title', format.comment)
            } else if (format.icon) {
                td.prepend('<i class="cell-icon fa fa-' + format.icon + '"></i>');
            }
        }

        if (format.progress && format.progresscolor) {
            let addProgress = function () {
                if (!span.isAttached()) {
                    setTimeout(addProgress, 50);
                } else {
                    let progress = Math.round(span.width() * parseInt(format.progress) / 100);
                    span.css('box-shadow', 'inset ' + progress.toString() + 'px 0px 0 0 ' + format.progresscolor);
                }
            };
            addProgress();
        }

        return td;
    },
    _getLoadingSpinner: function () {
        return $('<div class="text-center"><i class="fa fa-spinner"/></div>');
    },
    _colorizeElement: function (td, color, repeated) {
        let i = 10;

        let colorize = function () {
            if (i === 0) {
                td.css('box-shadow', '');
            } else {
                td.css('box-shadow', 'inset 0 0 100px 100px ' + App.hexToRGB(color, i / 10));
                i--;
                setTimeout(colorize, 50);
            }
        };

        colorize();

        return;


        var toColor = td.css('background-color');
        if (toColor === '') {
            var repeated = repeated || 0;
            if (repeated < 5) {
                var pcTable = this;
                setTimeout(function () {
                    pcTable._colorizeElement(td, color, repeated + 1)
                }, 50);
            }
            return;
        }
        if (toColor.substr(0, 1) != '#') {
            toColor = App.rgb2hex(toColor);
        }
        var parent = td;
        while (toColor == '#000000') {
            parent = parent.parent();
            toColor = parent.css('background-color');
            if (toColor.substr(0, 1) != '#') {
                toColor = App.rgb2hex(toColor);
            }
        }

        if (/(background\-color:[^;"]+;?)/.test(td.attr('style'))) {
            td.data('backgroundcolor', true);
        }

        this._TmpColorize(td, color, toColor);
    },
    _TmpColorize: function ($element, color, toColor) {

        var pcTable = this;
        var i = 0;
        var color = color || '#ff0000';
        var toColor = toColor || '#ffffff';
        var color2;

        if (toColor.substr(0, 1) != '#') {
            toColor = App.rgb2hex(toColor);
        }

        var shadeColor = function (color, percent, toColor) {
            var f = parseInt(color.slice(1), 16),
                toColor = parseInt(toColor.slice(1), 16),
                t1 = percent < 0 ? 0 : toColor >> 16,
                t2 = percent < 0 ? 0 : toColor >> 8 & 0x00FF,
                t3 = percent < 0 ? 0 : toColor & 0x0000FF,
                p = percent < 0 ? percent * -1 : percent,
                R = f >> 16,
                G = f >> 8 & 0x00FF,
                B = f & 0x0000FF;
            return "#" + (0x1000000 + (Math.round((t1 - R) * p) + R) * 0x10000 + (Math.round((t2 - G) * p) + G) * 0x100 + (Math.round((t3 - B) * p) + B)).toString(16).slice(1);
        }

        i++;
        if (i == 10) {
            color2 = toColor;
        } else {
            color2 = shadeColor(color, 0.1, toColor);
            if (color2 == color) color2 = toColor;
        }


        $element.css('background-color', color2);
        if (color2 != toColor) {
            setTimeout(function () {
                pcTable._TmpColorize($element, color2, toColor);
            }, 50)
        } else if (!$element.data('backgroundcolor')) {
            $element.attr('style', $element.attr('style').replace(/(background\-color:[^;"]+;?)/, ''));
        }

    }

});
$.extend(App.pcTableMain.prototype, {
    _addHorizontalDraggable: function () {
        this._innerContainer.off('mousedown.HorizontalDraggable mouseout').on('mousedown.HorizontalDraggable', function (event) {


            var originalEvent = event;
            while (originalEvent) {
                if ($(event.target).is('input')) return true;
                if (originalEvent == event.originalEvent)
                    break;
                originalEvent = event.originalEvent;
            }

            $(this)
                .data('x', event.clientX)
                .data('scrollLeft', this.scrollLeft);
            return false;
        }).on('mousemove', function (event) {
        
            if (event.buttons===1 && event.button === 0) {
                if (Math.abs($(this).data('x') - event.clientX) > 20) {
                    $(this).data('moved', true);
                    let timeout;
                    if (timeout) {
                        clearTimeout(timeout)
                    }
                    timeout = setTimeout(function () {
                        $(this).data('moved', false);
                    }, 200);

                    this.scrollLeft = $(this).data('scrollLeft') + $(this).data('x') - event.clientX;
                }
            }
        })
    }
});
App.pcTableMain.prototype._addRowPanel = function (panelId, row, buttons) {
    var panel = $('<div style="width: 165px;"><div class="buttons"></div></div>');
    if (buttons !== undefined) {
        var buttonsDiv = panel.find('.buttons').empty();
        $.each(buttons, function (text, $var) {
            if (typeof $var == 'function') {
                var btn = $('<button class="btn btn-sm btn-default">')
                    .html(text)
                    .on('click', $var);
            } else {
                if (typeof $var == 'object') {
                    if ($var.type == 'checkbox') {
                        btn = $('<input type="checkbox">');
                        if ($var.id) {
                            btn.attr('id', $var.id);
                        }
                        if ($var.func) {
                            btn.on('change', $var.func);
                        }
                        btn=btn.wrap('<span style="font-size: 10px; padding-left: 8px;" >').parent().append(' <span style="padding-top: 2px;">'+text+'</span>');
                    }
                }
            }
            buttonsDiv.append(' ');
            buttonsDiv.append(btn)
        })
    }
    row.on('remove', function () {
        panel.remove();
    });

    let pcTable = this;
    setTimeout(function () {
        let params = {
            'isParams': true,
            '$text': panel,
            'element': row,
            'container': pcTable._container,
            'placement': 'bottom',
            'trigger': 'manual'
        };
        App.popNotify(params);
        let popoverId = row.attr('aria-describedby');
        let popover = $('#' + popoverId).addClass('warning-bg');
        popover.find('.arrow').css('left', '80%');
        pcTable._positionPanel.call(pcTable, popover, row);
        panel.show()
    }, 50);

    return panel;

};
App.pcTableMain.prototype._positionPanel = function (panel, row) {
    var p = row.position();
    let left = this.tableWidth+10;
    if (this._innerContainer.width()>this.tableWidth){
        return panel.position({
            my: "right top",
            at: "right+2px bottom+"+12+"px",
            of: row
        })
    }else{
        return panel.position({
            my: "right top",
            at: "right+2px top+"+(p.top+47)+"px ",
            of: this._innerContainer
        })
    }

};
$.extend(App.pcTableMain.prototype, {
    _csvExport: function () {
        "use strict";
        let pcTable = this;
        this.model.csvExport(pcTable.dataSortedVisible, Object.keys(App.filter(pcTable.fields, (x, field) => !!field.showMeWidth))).then(function (json) {
            if (json.csv) {
                let blob = new Blob([json.csv], {type: "text/csv;charset=utf-8"});
                saveAs(blob, pcTable.tableRow.title + '.' + pcTable.model.tableData.updated.dt + '.csv');
            }
        })
    },
    _csvImportClick: function () {
        let pcTable = this;
        $('<input type="file" accept="text/csv">').on('change', function () {
            if (this.files && this.files[0]) {
                let reader = new FileReader();
                reader.onload = function (e) {
                    let csv = e.target.result;
                    pcTable._csvImportUpload.call(pcTable, csv);
                };
                reader.onerror = function (evt) {
                    console.log(evt.target.error);
                };
                reader.readAsDataURL(this.files[0]);
            }

        }).click();
    },
    _csvImportUpload: function (csv) {
        let pcTable = this;
        let answers = {};

        let csvImport = function () {
            pcTable.model.csvImport(csv, answers).then(function (json) {
                if (json.question) {
                    App.modal(json.question[1], 'Вопрос про csv-загрузку', {
                        'Отменить': 'close',
                        'Загружаем': function (block) {
                            "use strict";
                            block.modal('hide')
                            answers[json.question[0]] = 1;
                            csvImport();
                        }
                    })
                } else if (json.ok) {
                    pcTable.table_modify.call(pcTable, json)
                }
            })
        }
        csvImport();
    }
})
$.extend(App.pcTableMain.prototype, {
    ___fieldsHiddingShowAllButton: null,


    fieldsHiddingHide: function (fieldName, showMe) {
        let visibleFields = this.hiddingStorage.get(this.tableRow) || {};
        if (showMe) {
            visibleFields[fieldName] = this.fields[fieldName].width;
        } else {
            delete visibleFields[fieldName];
        }
        this.setVisibleFields(visibleFields);
    },
    setVisibleColumns: function () {
        let pcTable = this;
        this.fieldCategories.visibleColumns = [];
        this.fieldCategories.column.forEach(function (field) {
            if (field.showMeWidth) {
                pcTable.fieldCategories.visibleColumns.push(field);
            }
        })
    },
    setColumnWidth: function (name, width) {
        let visibleFields = this.hiddingStorage.get(this.tableRow) || {};
        visibleFields[name] = width;
        this.setVisibleFields(visibleFields);
    },
    loadVisibleFields: function () {
        let visibleFields = {};
        let storageDate = this.hiddingStorage.getDate(this.tableRow);
        if (!storageDate || (this.tableRow.fields_actuality != '' && this.tableRow.fields_actuality > storageDate)) {
            //Тут будет табличка про обновление настроек - когда-нибудь
            console.log('visibleFields updated');
            this.setVisibleFields(visibleFields, true, moment().format(App.dateTimeFormats.db));
        } else {
            visibleFields = this.hiddingStorage.get(this.tableRow) || {};
            this.setVisibleFields(visibleFields, true);
        }
    },
    setVisibleFields: function (visibleFields, isFromLoad, updatedDate) {
        let pcTable = this;
        if (visibleFields && Object.keys(visibleFields).length === 0) {
            visibleFields = {};
            Object.values(pcTable.fields).forEach(function (field) {
                pcTable.fields[field.name].showMeWidth = field.hidden ? 0 : field.width;
                visibleFields[field.name] = pcTable.fields[field.name].showMeWidth;
            });
        } else {
            Object.values(pcTable.fields).forEach(function (field) {
                if (field.category === 'filter') {
                    pcTable.fields[field.name].showMeWidth = field.width;
                } else if (visibleFields[field.name] !== undefined) {
                    pcTable.fields[field.name].showMeWidth = parseInt(visibleFields[field.name]);
                } else {
                    pcTable.fields[field.name].showMeWidth = isFromLoad && !field.hidden ? field.width : 0;
                }
                visibleFields[field.name] = pcTable.fields[field.name].showMeWidth;
            });
        }
        this.hiddingStorage.set(visibleFields, this.tableRow, updatedDate);
        /*
                let onlyVisibleFields = [];
                Object.keys(visibleFields).forEach(function (fieldName) {
                    if (visibleFields[fieldName] > 0) {
                        onlyVisibleFields.push(fieldName)
                    }
                });
                  $.cookie("tableViewFields" + this.tableRow.id, null, '/');
                  $.cookie("tableViewFields" + this.tableRow.id, null, {path: window.location.pathname});
          */

        this.setVisibleColumns();

        $.each(this.data, function (id, v) {
            delete pcTable.data[id].$tr;
        });

        if (this._header) {
            /*window.location.reload();
            return;*/

            this._refreshHead();
            this._refreshContentTable(true);
            this._rerenderColumnsFooter();
            this.fieldsHiddingGetButton();
            if (this.isCreatorView) {
                this._refreshHiddenFieldsBlock();
            }
            this.setWidthes();

            this.ScrollClasterized.insertToDOM(null, true);
        }

        if (this._insertRow) this._closeInsertRow();

    }, hideAdminViewFields: function () {
        let pcTable=this;
        let visibleFields = this.hiddingStorage.get(this.tableRow) || {};
        Object.keys(visibleFields).forEach(function (fieldName) {
            let fieldWidth=visibleFields[fieldName];
            if(fieldWidth>0){
                let field=pcTable.fields[fieldName];
                if(!field || field.webRoles && field.webRoles.length === 1 && field.webRoles[0] == "1"){
                    delete visibleFields[fieldName];
                }
            }
        });
        this.setVisibleFields(visibleFields);
    },
    setDefaultVisibleFields: function () {
        let fields = {};
        let pcTable = this;
        Object.values(pcTable.fields).forEach(function (field) {
            if (!field.hidden) {
                fields[field.name] = field.width;
            } else {
                fields[field.name] = 0;
            }
        });
        pcTable.setVisibleFields.call(pcTable, fields);
    },
    fieldsHiddingShowPanel: function () {
        let pcTable = this;
        let $fieldsDiv = $('<div class="hidding-form">');
        let lastCheck, dialog;

        const refreshDefaultEyeGroups = function (setsDiv) {

            setsDiv = setsDiv || $('#defaultEyeGroups'),
                sets = pcTable.tableRow.fields_sets || [];

            setsDiv.empty().append('<b>Наборы по умолчанию:</b> ');

            sets.forEach(function (set, i) {
                let link = $('<a href="#">').text(set.name).data('index', i);
                setsDiv.append(link.wrap('<span>').parent());
                if (pcTable.isCreatorView) {
                    if (i > 0) {
                        link.parent().append($('<button class="btn btn-xxs field_name"><i class="fa fa-arrow-left"></i></button>').data('index', i));
                    }
                    link.parent().append($('<button class="btn btn-xxs field_name"><i class="fa fa-remove"></i></button>').data('index', i));
                }
            });
            setsDiv.off();
            if (pcTable.isCreatorView) {
                setsDiv.on('click', '.btn', function () {
                    let i = $(this).find('i');
                    if (i.is('.fa-remove')) {
                        let remove = $(this);
                        pcTable.model.removeEyeGroupSet(remove.data('index')).then(function (json) {
                            pcTable.tableRow.fields_sets = json.sets;
                            refreshDefaultEyeGroups();
                        })
                    } else {
                        let leftMe = $(this);
                        pcTable.model.leftEyeGroupSet(leftMe.data('index')).then(function (json) {
                            pcTable.tableRow.fields_sets = json.sets;
                            refreshDefaultEyeGroups();
                        })
                    }
                });
            }
            setsDiv.on('click', 'a', function () {
                let index = $(this).data('index');
                let fields = sets[index]['fields'];
                if (Array.isArray(fields)) {
                    let _fields = {};
                    fields.forEach(function (fName) {
                        if (pcTable.fields[fName]) {
                            _fields[fName] = pcTable.fields[fName].width;
                        }
                    });
                    fields = _fields;
                }
                pcTable.setVisibleFields.call(pcTable, fields);
                dialog.close();
            });
        };

        let sets = pcTable.hiddingSetsStorage.getNames(pcTable.tableRow);
        if (sets && sets.length) {
            let setsDiv = $('<div class="fieldsHiddenSets">').appendTo($fieldsDiv);

            setsDiv.append('<b>Наборы:</b> ');

            sets.forEach(function (name) {
                let link = $('<a href="#">').text(name).data('name', name);
                setsDiv.append(link.wrap('<span>').parent());
                let span = link.parent();
                span.append($('<button class="btn btn-xxs" data-action="remove"><i class="fa fa-remove"></i></button>').data('name', name));
                if (pcTable.isCreatorView) {
                    span.append($('<button class="btn btn-xxs field_name" data-action="addDefaultSet" title="Сохранить как набор по умолчанию"><i class="fa fa-save"></i></button>').data('name', name));
                }
            });
            setsDiv.on('click', '.btn', function () {
                let remove = $(this),
                    name = remove.data('name');

                if (pcTable.isCreatorView && $(this).data('action') === 'addDefaultSet') {
                    let fields = pcTable.hiddingSetsStorage.get(pcTable.tableRow, name) || [];
                    pcTable.model.AddEyeGroupSet(name, fields).then(function (json) {
                        pcTable.tableRow.fields_sets = json.sets;
                        refreshDefaultEyeGroups();
                        pcTable.hiddingSetsStorage.remove(pcTable.tableRow, name);
                        remove.parent().remove();
                    });
                    return;
                }
                pcTable.hiddingSetsStorage.remove(pcTable.tableRow, name);
                remove.parent().remove();
            });


            setsDiv.on('click', 'a', function () {
                let a = $(this);
                let name = a.data('name');
                let fields = pcTable.hiddingSetsStorage.get(pcTable.tableRow, name) || [];
                pcTable.setVisibleFields.call(pcTable, fields);
                dialog.close();
            });
        }

        sets = pcTable.tableRow.fields_sets || [];

        let setsDiv = $('<div class="fieldsHiddenSets" id="defaultEyeGroups">').appendTo($fieldsDiv);

        if (sets && sets.length) {
            refreshDefaultEyeGroups(setsDiv);
        }


        $fieldsDiv.on('click', 'input[type="checkbox"]', function (event) {
            let input = $(this);
            let formDiv = input.closest('.hidding-form');
            if (event.shiftKey) {
                let index = formDiv.find('input').index(input);
                let _i = formDiv.find('input').index($(this));

                formDiv.find('input').each(function (i) {
                    if ((_i <= i && i < lastCheck) || (_i >= i && i > lastCheck)) {
                        $(this).prop('checked', input.is(':checked') ? 'checked' : false).trigger('change');
                    }
                });

            } else {
                lastCheck = formDiv.find('input').index($(this));
            }
        });

        let buttons = [
            {
                label: 'Применить',
                action: function (dialogRef) {
                    let fields = {};
                    $fieldsDiv.find('input:checked').each(function () {
                        let input = $(this);
                        fields[input.attr('name')] = parseInt(input.closest('div').find('input[type="number"]').val()) || null;
                    });
                    pcTable.setVisibleFields.call(pcTable, fields);
                    dialogRef.close();

                }
            },
            {
                label: 'По умолчанию',
                action: function (dialogRef) {
                    dialogRef.close();
                    pcTable.setDefaultVisibleFields.call(pcTable);

                }
            },
            {
                label: 'Показать все',
                action: function (dialogRef) {
                    dialogRef.close();
                    let fields = {};
                    Object.values(pcTable.fields).forEach(function (field) {
                        fields[field.name] = field.width;
                    });
                    pcTable.setVisibleFields.call(pcTable, fields);
                }
            },
            {
                label: 'Создать набор',
                action: function (dialogRef) {

                    let fields = {};
                    $fieldsDiv.find('input:checked').each(function () {
                        let input = $(this);
                        fields[input.attr('name')] = parseInt(input.closest('div').find('input[type="number"]').val()) || null;
                    });
                    pcTable.setVisibleFields.call(pcTable, fields);
                    dialogRef.close();

                    let $divSetName = $('<div></div>');
                    $divSetName.append('<div style="padding-top: 10px;"><label>Название набора</label><input type="text" id="fieldsSetName" class="form-control"/></div>')
                    BootstrapDialog.show({
                        message: $divSetName,
                        title: 'Сохранить набор полей',
                        buttons: [
                            {
                                label: 'Сохранить',
                                action: function (dialog) {
                                    let $input = $divSetName.find('#fieldsSetName');
                                    if ($input.val().trim() === '') {
                                        $input.addClass('error');
                                    } else {
                                        pcTable.hiddingSetsStorage.set(pcTable.tableRow, fields, $input.val().trim());
                                        dialog.close();
                                    }
                                }
                            },
                            {
                                label: 'Закрыть',
                                action: function (dialog) {
                                    dialog.close();
                                }
                            }
                        ],
                        draggable: true
                    })
                }

            },
            {
                label: 'Отмена',
                action: function (dialogRef) {
                    dialogRef.close();
                }
            }

        ];

        if (pcTable.isCreatorView) {
            buttons[1].label = "C админ полями";
            buttons[1].icon = "fa fa-angle-right";
            buttons.splice(1, 0, {
                label: 'Без админ полей',
                icon: "fa fa-angle-double-right",
                action: function (dialogRef) {
                    dialogRef.close();
                    pcTable.setDefaultVisibleFields.call(pcTable, true);

                }
            })
        }

        let categories = {'param': 'Хэдер', 'column': 'Колонки', 'footer': 'Футер'};
        Object.keys(categories).forEach(function (category) {

            if (pcTable.fieldCategories[category] && pcTable.fieldCategories[category].length) {

                $fieldsDiv.append('<div class="category-name">' + categories[category] + '</div>');
                $.each(pcTable.fieldCategories[category], function (k, field) {
                    let hidden = '';
                    if (field.hidden) {
                        hidden = ' (Скрыто по умолчанию)';
                    }
                    let fCheckbox = $('<div class="form-check no-bold"><label class="form-check-label"><input type="checkbox" name="' + field.name + '" class="form-check-input"> ' + field.title + hidden + '</label> <input type="number" placeholder="' + field.width + '" value="' + (field.showMeWidth && field.showMeWidth !== field.width ? field.showMeWidth : field.width) + '"/></div>');
                    if (field.showMeWidth) {
                        fCheckbox.find('input').prop('checked', true);
                        fCheckbox.attr('data-checked', true);
                    }
                    fCheckbox.appendTo($fieldsDiv);
                });

            }
        });

        $fieldsDiv.on('change', 'input[type="checkbox"]', function () {
            let div = $(this).closest('div');
            if ($(this).is(':checked')) {
                div.attr('data-checked', true);
            } else {
                div.removeAttr('data-checked');
            }
        });


        dialog = BootstrapDialog.show({
            message: $fieldsDiv,
            title: 'Видимость полей',
            buttons: buttons,
            draggable: true,
            onshow: function (dialog) {
                if (pcTable.isCreatorView) {
                    dialog.$modalContent.css({
                        width: "800px",
                    });
                }
            },
        })
    },
    fieldsHiddingGetButton: function (blinkMe) {
        "use strict";

        let pcTable = this;

        if (!this.___fieldsHiddingShowAllButton) {

            let timeout;

            this.___fieldsHiddingShowAllButton = $('<button class="btn btn-sm"><span class="fa fa-eye-slash"></span></button>')
                .on('click', function () {
                    pcTable.fieldsHiddingShowPanel.call(pcTable)
                }).on('contextmenu', function () {
                    if (!pcTable.isCreatorView) {
                        pcTable.setDefaultVisibleFields.call(pcTable);
                    } else if (timeout) {
                        clearTimeout(timeout);
                        timeout = null;
                        pcTable.hideAdminViewFields.call(pcTable, true);
                    } else {
                        timeout = setTimeout(function () {
                            pcTable.setDefaultVisibleFields.call(pcTable);
                            timeout = null;
                        }, 500);
                    }
                    return false;
                });
        }

        let isHidedExtraFields = Object.values(pcTable.fields).some(function (field) {
            if (field.showInWeb && !field.hidden && !field.showMeWidth) return true;
        });

        if (!isHidedExtraFields) {
            this.___fieldsHiddingShowAllButton.addClass('btn-default');
            this.___fieldsHiddingShowAllButton.removeClass('btn-warning');

        } else {
            this.___fieldsHiddingShowAllButton.addClass('btn-warning');
            this.___fieldsHiddingShowAllButton.removeClass('btn-default');
            if (blinkMe) {
                App.blink(this.___fieldsHiddingShowAllButton, 8, '#fff');
            }
        }
        return this.___fieldsHiddingShowAllButton;
    }
});
(function () {
    localStorage.removeItem('pcTableShowFields');

    let storageName = 'pcTableShowFieldsWithDates';
    let getTableId = function (tableRow) {
        let tableId = tableRow.id;
        if (tableRow.type === 'calcs') {
            tableId += '$' + tableRow.__version
        }
        return tableId;
    };


    App.pcTableMain.prototype.hiddingStorage = {
        set: function (fields, tableRow, dt) {

            let tableId = getTableId(tableRow);

            let sessionFilters = {};
            let filters = fields || {};
            try {
                sessionFilters = JSON.parse(localStorage.getItem(storageName)) || {};
            } catch (error) {

            }
            if (dt || !sessionFilters[tableId]) {
                sessionFilters[tableId] = [filters, dt];
            } else {
                sessionFilters[tableId][0] = filters;
            }
            localStorage.setItem(storageName, JSON.stringify(sessionFilters));
        },
        get: function (tableRow) {
            let tableId = getTableId(tableRow);
            return App.pcTableMain.prototype.hiddingStorage.getInner(tableId)[0];
        },
        getDate: function (tableRow) {
            let tableId = getTableId(tableRow);
            return App.pcTableMain.prototype.hiddingStorage.getInner(tableId)[1];
        },
        getInner: function (tableId) {
            let session, fields, date;
            try {
                session = JSON.parse(localStorage.getItem(storageName)) || {};
            } catch (error) {
                session = {};
            }
            return session[tableId] || []
        }
    };


    App.pcTableMain.prototype.hiddingSetsStorage = {
        set: function (tableRow, fields, name) {
            let tableId = getTableId(tableRow);

            let SetsFoFields = [];
            let storageName = 'pcTableShowFieldsSets' + tableId;

            let filters = fields || [];
            try {
                SetsFoFields = JSON.parse(localStorage.getItem(storageName)) || {};
            } catch (error) {

            }
            SetsFoFields[name] = filters;
            localStorage.setItem(storageName, JSON.stringify(SetsFoFields));
        },
        get: function (tableRow, name) {
            let tableId = getTableId(tableRow);
            let setFields;
            let storageName = 'pcTableShowFieldsSets' + tableId;
            try {
                setFields = JSON.parse(localStorage.getItem(storageName));
                setFields = setFields[name];
            } catch (error) {
            }

            if (setFields === null || setFields === undefined) setFields = undefined;
            return setFields;
        },
        getNames: function (tableRow) {
            let tableId = getTableId(tableRow);

            let setFields;
            let storageName = 'pcTableShowFieldsSets' + tableId;
            try {
                setFields = JSON.parse(localStorage.getItem(storageName));
                return Object.keys(setFields);
            } catch (error) {
            }
            if (setFields === null || setFields === undefined) setFields = undefined;
            return setFields;
        },
        remove: function (tableRow, name) {
            let tableId = getTableId(tableRow);
            let setFields;
            let storageName = 'pcTableShowFieldsSets' + tableId;
            try {
                setFields = JSON.parse(localStorage.getItem(storageName));
                delete setFields[name];
                localStorage.setItem(storageName, JSON.stringify(setFields));
            } catch (error) {
            }
        }
    };

})();
App.pcTableMain.prototype._print = function () {
    "use strict";
    let $printSettings = $('<div class="hidding-form">');

    const isAnyPrinfField = function (field) {
        if (field.showMeWidth) return true;
    };

    if (this.fieldCategories.param.length && this.fieldCategories.param.some(isAnyPrinfField)) {
        $printSettings.append('<div class="form-check no-bold"><label class="form-check-label"><input type="checkbox" name="params" class="form-check-input" checked="checked"> Параметры</label></div>');
    }
    if (this.fieldCategories.filter.length)
        $printSettings.append('<div class="form-check no-bold"><label class="form-check-label"><input type="checkbox" name="filters" class="form-check-input" checked="checked"> Фильтры</label></div>');
    if (this.fieldCategories.column.length && this.fieldCategories.column.some(isAnyPrinfField) && this.dataSortedVisible.length) {
        $printSettings.append('<div class="form-check no-bold"><label class="form-check-label"><input type="checkbox" name="rows" class="form-check-input" checked="checked"> Строчную часть</label></div>');
        $printSettings.append('<div class="form-check no-bold" style="padding-left: 20px;"><label class="form-check-label"><input type="checkbox" name="with-id" class="form-check-input"> с id</label></div>');
    }

    if (this._footersBlock.find('.val').length) {
        $printSettings.append('<div class="form-check no-bold"><label class="form-check-label"><input type="checkbox" name="column-footers" class="form-check-input" checked="checked"> Футеры колонок</label></div>');
    }
    if (this._footersSubTable.find('.val').length) {
        $printSettings.append('<div class="form-check no-bold"><label class="form-check-label"><input type="checkbox" name="other-footers" class="form-check-input" checked="checked"> Футеры вне колонок</label></div>');
    }

    let pcTable = this;
    let buttons = [
        {
            label: 'Печать',
            action: function (dialogRef) {
                let settings = [];
                $printSettings.find('input:checked').each(function () {
                    settings.push($(this).attr('name'));
                });
                dialogRef.close();
                pcTable._printTable.call(pcTable, settings);
            }
        },
        {
            label: 'Отмена',
            action: function (dialogRef) {
                dialogRef.close();
            }
        }
    ];

    BootstrapDialog.show({
        message: $printSettings,
        title: 'Печать',
        buttons: buttons,
        draggable: true
    })
};
App.pcTableMain.prototype._printTable = function (settings){

    let pcTable = this;
    let settingsObject = {
        fields: {}
    };
    if (settings.indexOf('with-id') !== -1)
        settingsObject.fields.id = 50;

    let categories = {
        params: pcTable.fieldCategories.param,
        filters: pcTable.fieldCategories.filter,
        rows: pcTable.fieldCategories.column,
        "column-footers": pcTable.fieldCategories.footer.filter(function (field) {
            return field.column!=="";
        }),
        "other-footers": pcTable.fieldCategories.footer.filter(function (field) {
            return field.column==="";
        }),

    };
    Object.keys(categories).forEach(function (category) {
        if (settings.indexOf(category) !== -1) {
            categories[category].forEach(function (field) {
                if (field.type === 'button' || field.showMeWidth<1 || !field.showMeWidth) return;
                settingsObject.fields[field.name] = field.showMeWidth;
            })
        }
    });

    if (settings.indexOf('rows') !== -1){
        settingsObject.ids = pcTable.dataSortedVisible;
    }
    settingsObject.sosiskaMaxWidth = 1100;

    pcTable.model.printTable(settingsObject);

};


App.pcTableMain.prototype.reOrderRows = function (btnId, $direction) {
    let pcTable = this;
    if (pcTable.tableRow.with_order_field && !pcTable.nSorted) {
        App.notify('Для работы поля порядок перезагрузите таблицу');
        return false;
    }


    let indBtn;
    let orderingRowIds = [];
    if (this.row_actions_get_checkedIds().length === 0) {
        orderingRowIds.push(btnId);
        indBtn = this.dataSorted.indexOf(btnId) + ($direction === 'after' ? 1 : -1);
        if (indBtn < 0) return;
    } else {
        if (pcTable.row_actions_get_checkedIds().indexOf(btnId) !== -1) {
            App.notify('В качестве якоря для перемещения нужно выбрать не отмеченную строку');
            return false;
        }
        let idsLength = this.row_actions_get_checkedIds().length;
        this.dataSorted.some(function (id, ind) {
            if (idsLength === 0) return true;
            if (pcTable.data[id].$checked) {
                orderingRowIds.push(id);
                --idsLength;
            }
        });
    }

    orderingRowIds.forEach(function (id) {
        pcTable.dataSorted.splice(pcTable.dataSorted.indexOf(id), 1);
    });

    let idInd;
    if (indBtn !== undefined) {
        idInd = indBtn;
    } else {
        idInd = this.dataSorted.indexOf(btnId) + ($direction === 'after' ? 1 : 0)
    }

    pcTable.dataSorted.splice(idInd, 0, ...orderingRowIds);

    this.dataSortedVisible = [];
    pcTable.dataSorted.forEach(function (id) {
        if (pcTable.data[id].$visible) pcTable.dataSortedVisible.push(id);
    });

    pcTable._refreshContentTable();

    if (pcTable.tableRow.with_order_field) {
        $('table.pcTable-table').addClass('reordered');
       // pcTable._table.addClass('reordered');
    }
    pcTable.row_actions_uncheck_all();
};
App.pcTableMain.prototype.reOrderRowsSave = function () {
    let pcTable = this;
    /*if (pcTable.notCorrectOrder) {
        App.notify('Поля выбраны с промежутками - выберите корректный фильтр');
        return;
    }*/
    pcTable._orderSaveBtn.prop('disabled', true).find('i').attr('class', 'fa fa-cog');


    this.model.saveOrder(this.dataSorted)
        .then(function (json) {
            pcTable.table_modify(json);
            pcTable._orderSaveBtn.prop('disabled', false).find('i').attr('class', 'fa fa-save');
            $('table.pcTable-table').removeClass('reordered');
            //pcTable._table.removeClass('reordered');

        });

};
App.pcTableMain.prototype.addReOrderRowBind = function () {

    let pcTable = this;
    pcTable._innerContainer.on('click', 'td.n button', function (event) {
        let btn = $(this);

        if (!pcTable.tableRow.with_order_field || pcTable.__getCheckedRowsIds(undefined, true, 'blockorder')) {

            pcTable.reOrderRows.call(pcTable, pcTable._getItemByTr.call(pcTable, btn.closest('tr')).id, btn.find('.fa-angle-up').length === 1 ? 'before' : 'after');
        }
    });
};

App.pcTableMain.prototype.__formatFunctions={
    blockadd: function () {
        this._rowsButtons();
    },
    blockorder: function () {
        this._refreshHead();
    },
    block: function () {
        this._refreshParamsBlock();
        this._refreshContentTable(true);
        this._refreshFootersBlock();
    },
};


    $.extend(App.pcTableMain.prototype, {
            setWidthes: function () {
                "use strict";
                let TreeWidth = $('body>.page_content:first').is('.tree-minifyed') ? 5 : 300;
                this.width = $('body').width() - TreeWidth;
                this._container.width(this.width);
                this._innerContainer.width(this.width - 80);

                let $block;
                this._paramsBlock.replaceWith($block = this._createParamsBlock());
                this._paramsBlock = $block;
                this._refreshParamsBlock();

                this._filtersBlock.replaceWith($block = this._createFiltersBlock());
                this._filtersBlock = $block;

                this._refreshFiltersBlock(this.data_params);


                let footers = this._createFootersBlock();
                this._footersSubTable.replaceWith(footers[1]);
                this._footersSubTable = footers[1];
                this._footersBlock.replaceWith(footers[0]);
                this._footersBlock = footers[0];

                this._refreshFootersBlock();


                if (this.tableWidth < this._innerContainer.width()) {
                    this.__$rowsButtons.width(this.tableWidth - 59)
                } else {
                    this.__$rowsButtons.width(this._innerContainer.width())
                }

                if (this._container.width() < this._table.width()) {
                    this._addHorizontalDraggable();
                }
                this._container.height(window.innerHeight - this._container.offset().top - 20);
            },
            initForPanel: function (config) {
                $.extend(true, this, config);
                this.refreshArraysFieldCategories(false);
                let data = {};
                this.__checkedRows = [];
                this.data.map(function (item) {
                    this.dataSorted.push(item.id);
                    this.dataSortedVisible.push(item.id);
                    data[item.id] = item;
                    data[item.id].$checked = -1 !== this.__checkedRows.indexOf(item.id) ? true : false;
                }, this);
                this.data = data;
                this.model.setDataRows(this.data);
            },
            _init: function () {
                let pcTable = this;

                this._container.addClass(this.contanerClass).addClass('pcTable-type-' + this.tableRow.type);

                let navTopLine = $('#nav-top-line');
                navTopLine.addClass('pcTable-type-' + this.tableRow.type);
                if (this.tableRow.type === 'tmp') {
                    navTopLine.text('Будьте внимательны - это временная таблица');
                }

                this._innerContainer = $('<div class="innerContainer">');


                $('body').on('keyup', function (event) {
                    if (event.which === 27) {
                        pcTable._container.trigger('escPressed');
                    }
                });

                this._container.append(this._innerContainer);
                this.addReOrderRowBind();

                let data = {};
                this.__checkedRows = [];

                this.data.map(function (item) {
                    this.dataSorted.push(item.id);
                    this.dataSortedVisible.push(item.id);
                    data[item.id] = item;
                    data[item.id].$checked = -1 !== this.__checkedRows.indexOf(item.id) ? true : false;
                }, this);
                this.data = data;


                this.model.setDataRows(this.data);
                let timeoutResize;
                $(window).resize(function () {
                    if (timeoutResize) clearTimeout(timeoutResize);
                    timeoutResize = setTimeout(function () {
                        pcTable.setWidthes();
                    }, 500);
                });

            },
            refreshArraysFieldCategories: function (forTable) {
                "use strict";
                forTable = forTable || false;

                let pcTable = this;
                pcTable.hidden_fields = pcTable.hidden_fields || {};

                $.each(pcTable.hidden_fields, function (k, field) {
                    pcTable.hidden_fields[k] = $.extend({}, field, fieldTypes[field.type], field);
                    pcTable.hidden_fields[k].isHiddenField = true;
                });

                pcTable.mainFieldName = 'id';

                pcTable.fieldCategories = {};

                ['param', 'column', 'filter', 'footer'].forEach(function (category) {
                    pcTable.fieldCategories[category] = [];
                });

                let withoutCategories = [];
                try {
                    withoutCategories = JSON.parse(decodeURIComponent(window.location.hash.substring(1)) || '[]');
                    if (!withoutCategories || !withoutCategories.wc) {
                        withoutCategories = [];
                    } else {
                        withoutCategories = withoutCategories.wc;
                    }
                } catch (e) {
                }

                const initField = function (name, field) {
                    field.pcTable = pcTable;

                    if (withoutCategories.indexOf(field.category) !== -1) return;

                    if (fieldTypes[field.type]) {
                        field = $.extend({}, defaultField, fieldTypes[field.type], field);
                    } else {
                        field = $.extend({}, defaultField, field);
                    }


                    pcTable.fields[name] = field;

                    if (field.showInWeb) {
                        pcTable.fieldCategories[field.category].push(field);
                    } else if (field.name) {
                        pcTable.hidden_fields[field.name] = field;
                    }
                };

                if (forTable) {
                    let n = {type: "n"};
                    initField('n', n);
                    let _fields = $.extend({}, this.fields);
                    delete _fields.n;
                    $.each(_fields, initField);
                } else {
                    $.each(this.fields, initField);
                }

                if (this.tableRow.main_field && this.fields[this.tableRow.main_field]) {
                    pcTable.mainFieldName = this.tableRow.main_field;
                }

            },
            render: function (addVars) {
                let pcTable = this;

                this.loadVisibleFields();


                this._renderTable();


                if (this._sorting.addSortable) {
                    this._sorting.addSortable(this);
                }
                this._addSelectable();
                this._addEditable();
                this._addSave();

                this.row_actions_add();

                this.setWidthes();

                this.__addFilterable();
                this._refreshHead();


                this.ScrollClasterized = this.Scroll();

                if (pcTable.checkIsUpdated > 0) {
                    let timeout = parseInt(pcTable.checkIsUpdated) * 2000;
                    setTimeout(function () {
                        pcTable.checkTableIsChanged.call(pcTable, timeout)
                    }, timeout);
                }
                if (window.parent === window && pcTable.checkForNotifications > 0) {
                    pcTable.checkForNotificationsFunc.call(pcTable)
                }

                this.refresh();
                this.__applyFilters();
                if (addVars) {
                    pcTable._addInsert(addVars);
                }

            },
            _addSave: function () {
                $('body').on('keyup', function (event) {
                    if (event.ctrlKey || event.metaKey) {
                        if (String.fromCharCode(event.which).toLowerCase() === 's' && $('#bigOneCodemirror').length === 0) {
                            $('body').trigger('ctrlS')
                        }
                    }
                });

            },

            reloaded: function () {

                let notify = $('#refresh-notify');
                if (notify.length) {
                    notify.closest('.alert').remove();
                    this.checkTableIsChanged(parseInt(this.checkIsUpdated) * 2000);
                }
            },
            checkForNotificationsFunc: function () {
                let pcTable = this;
                let RequestObject = {};
                let activeNotifications = {};

                const check = function () {
                    switch (document.visibilityState) {
                        case 'hidden':
                            if (RequestObject.jqXHR && RequestObject.jqXHR.abort)
                                RequestObject.jqXHR.abort();
                            RequestObject = {};
                            break;
                        case 'visible':

                            pcTable.model.checkForNotifications(pcTable.checkForNotifications, Object.keys(activeNotifications), RequestObject).then(function (json) {
                                if (json.notifications && json.notifications.length) {


                                    activeNotifications[json.notification_id] = App.showDatas.call(pcTable.model, json.notifications, json.notification_id);

                                    let deffs = [];
                                    activeNotifications[json.notification_id].forEach(function (dialog) {
                                        if (dialog) {
                                            let def = $.Deferred();

                                            dialog.$modal.on('hide.bs.modal', function () {
                                                def.resolve();
                                            });
                                            deffs.push(def)
                                        }
                                    });

                                    $.when(...deffs).then(function () {
                                        delete activeNotifications[json.notification_id];
                                    })
                                }
                                if (json.deactivated && json.deactivated.forEach) {
                                    json.deactivated.forEach(function (id) {
                                        if (activeNotifications[id]) {
                                            activeNotifications[id].forEach(function (dialog) {
                                                dialog.simpleClose();
                                            });
                                            delete activeNotifications[id];
                                        }
                                    })
                                }
                                check.call(pcTable);

                            })
                    }
                };

                document.addEventListener("visibilitychange", check);
                check();

            },
            checkTableIsChanged: function (timeout) {
                let pcTable = this;

                if (document.hidden) {
                    setTimeout(function () {
                        pcTable.checkTableIsChanged.call(pcTable, timeout);
                    }, 1000);
                } else {
                    pcTable.model.checkTableIsChanged.call(pcTable.model).then(function (json) {
                        if (json.no || pcTable.model.tableData.updated.code === json.code) {
                            pcTable.checkTableIsChanged.call(pcTable, timeout);

                        } else {
                            let checkIsNotAlreadyChanged = function () {
                                if (pcTable.model.tableData.updated.code === json.code) {
                                    pcTable.checkTableIsChanged.call(pcTable, timeout);
                                } else {

                                    $.notify({
                                        message: '<div id="refresh-notify"><button class="btn btn-warning btn-sm" style="margin-right: 20px;">' +
                                            'Обновить</button> <span>Таблица была изменена пользователем <b>' +
                                            json.username + '</b> в <b>' + App.dateFormats.covert(json.dt, 'YY-MM-DD HH:mm', 'HH:mm DD.MM')
                                            + '</b> </span></div>'
                                    }, {
                                        type: 'warning',
                                        allow_dismiss: false,
                                        delay: 0
                                    });

                                    $('#refresh-notify button').on('click', function () {
                                        pcTable.model.refresh()
                                    });
                                }
                            };
                            pcTable.model.doAfterProcesses(
                                function () {
                                    setTimeout(checkIsNotAlreadyChanged, 200)
                                }
                            )

                        }
                    });
                }

            }
            ,
            _getTableMainFieldName: function (fields, mainFieldId) {
                let fieldName;
                Object.keys(fields).some(function (f) {
                    let field = fields[f];
                    if (field.id == mainFieldId) {
                        fieldName = field.name;
                        return true;
                    }
                });
                return fieldName;
            },
            _getFieldbyName: function (fieldName) {
                return this.fields[fieldName];
            },
            _getColumnIndexByTd: function (td, tr) {
                var tr = tr || td.closest('tr');
                return tr.find('td').index(td);
            },
            _fieldByTd: function (td, tr) {
                let cIndex = this._getColumnIndexByTd(td, tr);
                return this.fieldCategories.visibleColumns[cIndex - 1];
            },
            _getRowIndexById: function (id) {
                var index = null;
                let p = this;
                for (let i in p.data) {
                    if (p.data[i].id == id) {
                        return i;
                        break;
                    }
                }
                return null;
            },
            _getFieldBytd: function (td) {
                if (!td.closest('tr').is('.DataRow')) {
                    return this.fields[td.data('field')]
                } else {
                    return this.fieldCategories.visibleColumns[td.closest('tr').find(td.prop('tagName')).index(td) - 1]
                }

            },
            _isParamsArea: function ($obj) {
                return $obj.closest('table').is('.pcTable-paramsTable')
            },
            _isFootersArea: function ($obj) {
                return $obj.closest('tbody').is('.pcTable-footers')
            },
            _getItemBytd: function (td) {
                let tr = td.closest('tr');
                return this._getItemByTr(tr);
            },
            _getItemByTr: function (tr) {
                if (!tr.is('.DataRow')) {
                    return this.data_params;
                }
                return this.data[tr.data(pcTable_ROW_ItemId_KEY)];
            },
            _getItemById: function (id) {
                return this.data[id];
            },
            _deleteItemById: function (id) {
                let item = this._getItemById(id);
                if (item && item.$tr) {
                    item.$tr.remove();
                }
                ['dataSorted', 'dataSortedVisible', '__checkedRows'].some(function (array) {
                    let ind = this[array].indexOf(id);
                    if (ind !== -1) {
                        this[array].splice(ind, 1);
                    }
                }, this);
                delete this.data[id];
            },
            _getTdByFieldName: function (fieldName, tr) {
                let fieldIndex = 0;
                this.fieldCategories.visibleColumns.every(function (field, index) {
                    if (fieldName == field.name) {
                        fieldIndex = index;
                        return false;
                    }
                    return true;
                });
                return this._getTdByColumnIndex(tr, fieldIndex + 1);
            },
            _getTdByColumnIndex: function (tr, index) {
                return tr.find('td:eq(' + index + ')');
            },
            refresh: function () {
                this._refreshTitle();
                this._refreshParamsBlock();
                this._refreshFiltersBlock(this.data_params);
                this._refreshFootersBlock();

                this._refreshContentTable();

            }

        }
    );


})(window, jQuery);
(function () {
    if (!App.models)
        App.models = {};

    App.models.table = function (url, tableData, extraData) {
        let inProcess = false;
        let iframeNum = 0;
        let dataRows;
        let pcTable;
        let startedQuery = null;

        const getFilteredItem = function (item) {
            let itemTmp = {};
            Object.keys(item).forEach(function (k) {
                //Фильтруем jquery-объекты из item
                if (!/^\$/.test(k)) {
                    if (k === 'id') {
                        itemTmp[k] = item[k];
                    } else {
                        if (item[k] !== null && typeof item[k] === 'object' && Object.keys(item[k]).indexOf('v') !== -1) {
                            itemTmp[k] = item[k]['v'];
                        } else {
                            itemTmp[k] = item[k];
                        }
                    }
                }
            });
            return itemTmp;
        };


        extraData = extraData || {};
        var obj = {
            setDataRows: function (data) {
                dataRows = data;
            },
            addFiltersData: function (filters) {
                "use strict";
                extraData = $.extend(true, {}, extraData, filters);
            },
            tableData: tableData,
            url: url,
            isInProcess: function () {
                return inProcess;
            },
            doAfterProcesses: function (func) {
                let model = this;
                setTimeout(function () {
                    let def;
                    if (def = model.getDefferedProcess()) {
                        def.then(func);
                    } else func();
                }, 50);
            },
            getDefferedProcess: function () {
                if (!inProcess) return false;
                let def = $.Deferred();

                const checkInProcess = function () {
                    if (inProcess) {
                        setTimeout(checkInProcess, 50);
                    } else {
                        def.resolve();
                    }
                };

                checkInProcess();
                return def.promise();

            },
            showLinks: function (json) {
                App.showLInks(json.links, pcTable.model);
                delete json.links;
            }, shoInterfaceDatas: function (json) {
                App.showDatas.call(pcTable.model, json.interfaceDatas);
                delete json.interfaceDatas;
            }, showPanels: function (json) {
                App.showPanels(json.panels);
                delete json.panels;
            },
            addPcTable: function (pcTableIn) {
                pcTable = pcTableIn;
            },
            __ajax: function ($method, $data, RequestObject, withoutLoading, filters) {
                "use strict";
                let url = this.url;

                let $d = $.Deferred();
                filters = filters || {};
                let isDone = false;
                let isShownCog = false;

                let data_tmp = $.extend(true, {}, $data, {tableData: tableData, ajax: true}, extraData, filters);

                if ($data.method === 'checkTableIsChanged' || $data.method === 'checkForNotifications') {
                    if ($data.method === 'checkForNotifications') {
                        url = '/nobuffer/index.php';
                    }
                    data_tmp = $.extend({}, $data, {code: tableData.updated.code, ajax: true}, extraData);
                    if (tableData.sess_hash) {
                        data_tmp.tableData = {sess_hash: tableData.sess_hash};
                    }
                } else {
                    inProcess = true;
                    if (!withoutLoading)
                        setTimeout(function () {
                            if (!isDone) {
                                App.fullScreenProcesses.showCog();
                                isShownCog = true;
                            }
                        }, 1000);

                }
                if (data_tmp.data !== undefined && typeof data_tmp.data === 'object') {
                    data_tmp.data = JSON.stringify(data_tmp.data);
                }
                if (dataRows) {
                    data_tmp.ids = JSON.stringify(Object.keys(dataRows));
                }

                let Model = this;
                let success = function (json) {

                    let methods = {
                        'edit': 'Изменение',
                        'checkInsertRow': 'Предварительное добавление',
                        'duplicate': 'Дублирование',
                        'refresh_rows': 'Пересчет строк',

                        'getTableData': 'Загрузка информации о таблице',
                        'refresh': 'Обновление данных таблицы',
                        'checkEditRow': 'Предварительный расчет панели',
                        'saveEditRow': 'Сохранение панели',
                        'save': 'Изменение поля',
                        'click': 'Нажатие кнопки',
                        'selectSourceTableAction': 'Вызов панели',
                        'add': 'Добавление строки',
                        'getEditSelect': 'Загрузка селекта',
                        'delete': 'Удаление'
                    };

                    let pcTableObj = $('#table').data('pctable');
                    if (pcTableObj) {
                        if (json.LOGS) {
                            if (!pcTableObj.LOGS) pcTableObj.LOGS = {};
                            pcTableObj.LOGS = $.extend(pcTableObj.LOGS, json.LOGS);
                        }
                        if (json.FullLOGS) {
                            if (!pcTableObj.FullLOGS) pcTableObj.FullLOGS = [];

                            let logs = {'text': (methods[data_tmp['method']] || data_tmp['method'])};
                            logs.children = json.FullLOGS;
                            if (json.FullLOGS.length) {
                                pcTableObj.FullLOGS.push(logs);
                                App.blink(pcTableObj.LogButton, 8, '#fff');
                            }
                        }
                        if (json.FieldLogs) {
                            pcTableObj.FieldLOGSName = methods[data_tmp['method']] || data_tmp['method'];
                            pcTableObj.FieldLOGS = json.FieldLogs;
                        }
                    }


                    if (!json.error) {
                        if (json.reload) window.location.href = window.location.href;
                        else {
                            if (json.links && json.links.length > 0) Model.showLinks(json);
                            if (json.interfaceDatas && json.interfaceDatas.length > 0) Model.shoInterfaceDatas(json);
                            if (json.panels && json.panels.length > 0) Model.showPanels(json);
                        }
                        $d.resolve(json);
                    } else {
                        var errorText = $('<div>').html(json.error.replace(/\[\[(.*?)\]\]/g, '<b>$1</b>'));

                        if (json.log) {
                            let btn = $('<button class="btn btn-xxs btn-danger"><i class="fa fa-info" style="padding-top: 3px;" aria-hidden="true"> c</i></button>');
                            btn.on('click', function () {
                                BootstrapDialog.show({
                                    message: $('<pre style="max-height: ' + ($('body').height() - 200) + 'px; overflow: scroll">').css('font-size', '11px').text(JSON.stringify(json.log, null, 1)),
                                    type: BootstrapDialog.TYPE_DANGER,
                                    title: 'Лог расчета',
                                    buttons: [{
                                        'label': "Закрыть",
                                        cssClass: 'btn-m btn-default',
                                        'action': function (dialog) {
                                            dialog.close();
                                        }
                                    }],
                                    draggable: true,
                                    onshown: function (dialog) {
                                        dialog.$modalContent.position({
                                            of: window
                                        })
                                    },
                                    onshow: function (dialog) {
                                        dialog.$modalHeader.css('cursor', 'pointer')
                                        dialog.$modalContent.css({
                                            width: 1200
                                        });
                                    }

                                });

                            });
                            errorText.append(' ');
                            errorText.append(btn)
                        }
                        App.notify(errorText)
                        $d.reject();
                    }
                }, fail = function (obj) {
                    let error, timeout;
                    if (obj && obj.status === 200) {
                        if (obj.responseJSON && obj.responseJSON.error) error = obj.responseJSON.error;
                        else {
                            error = $('<div>Ошибка выполнения операции  </div>');
                            if (pcTable && pcTable.isCreatorView) {
                                error.append('<button class="btn danger-backg btn-xs" data-toggle="collapse" data-target="#notify-texh"><i class="fa fa-angle-down"></i><i class="fa fa-angle-up"></i></button>');
                                error.append($('<div id="notify-texh" class="collapse">').append($('<code>').text(obj.responseText)));
                                if (!obj.responseText) {
                                    console.log(obj, RequestObject);

                                }
                            }
                        }
                    } else {

                        if (!RequestObject && obj && obj.statusText != "abort" && obj.statusText != "error") {
                            error = obj.statusText;
                            timeout = 200;

                        } else if (RequestObject && RequestObject.jqXHR) {
                            if (RequestObject.jqXHR.statusText !== "abort") {
                                error = 'Нет соединения с сервером';
                                timeout = 200;
                            }
                        }
                    }

                    if (error) {
                        if (timeout) {
                            setTimeout(function () {
                                App.notify(error);
                            }, timeout)
                        } else {
                            App.notify(error);
                        }

                    }

                    $d.reject(obj);
                };


                let ajax = function () {

                    //Чтобы не блокировала защищенная подсеть
                    if (!(/^\/nobuffer/).test(url)) {
                        if (((new Date()).getTime() - startedQuery) < 150) {
                            setTimeout(ajax, 50);
                            return;
                        }
                        startedQuery = (new Date()).getTime();
                        if (/\?/.test(url)) {
                            url += '&';
                        } else url += '?';
                        url += 'rn=' + Math.round(Math.random() * 100000) + ( data_tmp['method'] || '');
                    }
                    $.ajax({
                        url: url,
                        method: $method,
                        data: data_tmp,
                        dataType: 'json',
                        beforeSend: function (jqXHR, settings) {
                            if (RequestObject) {
                                RequestObject.jqXHR = jqXHR;
                            }

                        }
                    }).then(success).fail(fail)
                };
                ajax();


                let cleanProcess = function () {

                    inProcess = false;
                    isDone = true;
                    if (isShownCog) {
                        App.fullScreenProcesses.hideCog();
                    }
                };

                $d.always(function () {
                    setTimeout(cleanProcess, 100);
                });

                return $d.promise();

            },
            delete: function (ids) {
                if (ids.length === 0)
                    return false;

                return this.__ajax('post', {delete_ids: JSON.stringify(ids), method: 'delete'});
            },
            duplicate: function (ids, unic_replaces, insertAfter) {
                if (ids.length === 0)
                    return false;

                return this.__ajax('post', {
                    duplicate_ids: JSON.stringify(ids),
                    data: unic_replaces,
                    insertAfter: insertAfter,
                    method: 'duplicate'
                });
            },
            getFieldLog: function (fieldName, rowId, rowName) {
                return this.__ajax('post', {field: fieldName, id: rowId, method: 'getFieldLog', rowName: rowName});
            },
            refresh_rows: function (ids) {
                if (ids.length === 0)
                    return false;

                return this.__ajax('post', {refreash_ids: JSON.stringify(ids), method: 'refresh_rows'});
            },
            refresh_cycles: function (ids) {
                if (ids.length === 0)
                    return false;

                return this.__ajax('post', {refreash_ids: JSON.stringify(ids), method: 'refresh_cycles'});
            },
            checkUnic: function (fieldName, val) {
                "use strict";
                return this.__ajax('post', {fieldName: fieldName, fieldVal: val, method: 'checkUnic'});
            },
            add: function (data) {
                return this.__ajax('post', {data: data, method: 'add'});
            },
            getValue: function (data, table_id) {
                return this.__ajax('post', {data: data, method: 'getValue', table_id: table_id});
            },
            __setInProcess: function (inp) {
                inProcess = inp
            },
            get: function (id) {
                return this.__ajax('get', {id: id});
            },
            checkInsertRow: function (data, savedFieldName) {
                var sendData = {};
                $.each(data, function (k, v) {
                    if (v != undefined) {
                        sendData[k] = v;
                    }
                });
                return this.__ajax('post', {data: sendData, savedFieldName: savedFieldName, method: 'checkInsertRow'});
            },
            checkEditRow: function (data) {
                var sendData = {};
                $.each(data, function (k, v) {
                    if (v != undefined) {
                        sendData[k] = v;
                    }
                });
                return this.__ajax('post', {data: sendData, method: 'checkEditRow'});
            },
            checkTableIsChanged: function () {
                return this.__ajax('post', {
                    method: 'checkTableIsChanged',
                    table_id: pcTable.tableRow.id,
                    cycle_id: pcTable.tableRow.cycle_id
                });
            },
            checkForNotifications: function (periodicity, activeNotifications, RequestObject) {
                return this.__ajax('post', {
                    method: 'checkForNotifications',
                    periodicity: periodicity,
                    activeIds: activeNotifications,
                }, RequestObject);
            }
            , notificationUpdate: function (id, type) {
                return this.__ajax('post', {
                    method: 'notificationUpdate',
                    id: id,
                    type: type,
                });
            },
            selectSourceTableAction: function (field_name, data) {
                return this.__ajax('post', {
                    field_name: field_name,
                    data: data,
                    method: 'selectSourceTableAction'
                });
            },
            saveEditRow: function (data) {
                var sendData = {};
                $.each(data, function (k, v) {
                    if (v !== undefined) {
                        sendData[k] = v;
                    }
                });
                return this.__ajax('post', {data: sendData, method: 'saveEditRow'});
            },
            getEditSelect: function (item, fieldName, q, parentid, withLoading) {
                var sendData = {};
                return this.__ajax('post', {
                    data: {item: item, field: fieldName},
                    q: q,
                    parentId: parentid,
                    method: 'getEditSelect'
                }, undefined, !withLoading);
            },
            loadPreviewHtml: function (fieldName, item, val) {
                return this.__ajax('post', {
                    data: {item: getFilteredItem(item), field: fieldName, val: val},
                    method: 'loadPreviewHtml'
                }, null, true);
            },
            save: function (data) {
                let _filters = {};
                if (data.params) {
                    Object.keys(data.params).some(function (fName) {
                        if (pcTable.fields[fName].category === 'filter') {
                            if (pcTable._filtersBlock.data('cryptoFilters')) {
                                _filters.filters = pcTable._filtersBlock.data('cryptoFilters');
                            }
                            return true;
                        }
                    });
                }
                return this.__ajax('post', {data: data, method: 'edit'}, null, null, _filters);
            },
            click: function (data) {
                return this.__ajax('post', {data: data, method: 'click'});
            },
            csvExport: function (idsVisibleSorted, visibleFields) {
                return this.__ajax('post', {
                    method: 'csvExport',
                    sorted_ids: JSON.stringify(idsVisibleSorted),
                    visibleFields: JSON.stringify(visibleFields)
                });
            },
            csvImport: function (csv, answers) {
                return this.__ajax('post', {csv: csv, answers: answers, method: 'csvImport'});
            },
            getTableData: function (sess_hash) {
                return this.__ajax('post', {method: 'getTableData', tableData: {sess_hash: sess_hash}});
            },
            refresh: function (func) {

                func = func || function (json) {
                    pcTable.table_modify.call(pcTable, json);
                    pcTable.reloaded.call(pcTable);
                };
                this.__ajax('post', {method: 'refresh'}).then(func)
            },
            saveOrder: function (ids) {
                return this.__ajax('post', {method: 'saveOrder', orderedIds: JSON.stringify(ids)});
            },
            setCommentsViewed: function (nums, field_name, id) {
                return this.__ajax('post', {method: 'setCommentsViewed', nums: nums, field_name: field_name, id: id});
            },
            AddEyeGroupSet: function (name, fields) {
                return this.__ajax('post', {method: 'addEyeGroupSet', name: name, fields: fields});
            },
            removeEyeGroupSet: function (index) {
                return this.__ajax('post', {method: 'removeEyeGroupSet', index: index});
            },
            leftEyeGroupSet: function (index) {
                return this.__ajax('post', {method: 'leftEyeGroupSet', index: index});
            },
            reUser: function (userId) {
                return this.__ajax('post', {method: 'reuser', userId: userId});
            },
            printTable: function (settings) {
                return this.__ajax('post', {method: 'printTable', settings: JSON.stringify(settings)});
            }
            ,
            getAllTables: function () {
                return this.__ajax('post', {method: 'getAllTables'});
            },
            calcFieldsLog: function (data, name) {
                return this.__ajax('post', {method: 'calcFieldsLog', calc_fields_data: data, name: name})
            },
            renameField: function (name) {
                return this.__ajax('post', {method: 'renameField', name: name})
            }

        };
        return obj
    }
})();
// import ca from "../../../bower_components/moment/src/locale/ca";

(function () {
    addTree = function (prefix, data, isCreatorView) {

        if (! App.isTopWindow()) {
            $('div.page_content').addClass('tree-minifyed iframed');
            return false;
        }

        $.jstree.defaults.core.dblclick_toggle = false;
        $.jstree.defaults.core.expand_selected_onload = true;
        $.jstree.defaults.core.force_text = true;
        $.jstree.link_prefix = prefix;


        let treeStorage = localStorage.getItem('tree') || '{}';
        treeStorage = JSON.parse(treeStorage);

        $.each(data, function (k, v) {
            data[k]['data']={'type':v.type}
            if (v.state && v.state.selected) {
                data[k]["li_attr"] = {"class": "jstree-selected"}
            }
            if (treeStorage[v.id]) {
                if (!data[k].state) data[k].state = {};
                data[k].state.opened = true;
            }
            if (v.href) {
                data[k]["a_attr"] = {"href": $.jstree.link_prefix + v.href}
            }else if (v.link) {
                data[k]["a_attr"] = {"href": v.link}
            }
            if (isCreatorView) {
                switch (v.type) {
                    case 'folder':
                        data.push({
                            type: 'plus'
                            , id: 'plus-table' + v.id.substring(4)
                            , text: 'Таблицу'
                            , parent: v.id
                            , li_attr: {class: "jstree-creatorView"}
                        });
                        data.push({
                            type: 'plus'
                            , id: 'plus-folder' + v.id.substring(4)
                            , text: 'Папку/Ссылку'
                            , parent: v.id
                            , li_attr: {class: "jstree-creatorView"}
                        });

                        break;
                    case 'cycle_name':
                        data.push({
                            type: 'plus'
                            , id: 'plus-calcs' + v.parent.substring(4)
                            , text: 'Таблицу'
                            , parent: v.id
                            , li_attr: {class: "jstree-creatorView"}
                        });
                        break;
                }
            }
        });


        let $leftTree = $('#leftTree');

        $leftTree.on('loaded.jstree after_open.jstree', function () {
            let treeWidth=$leftTree.width();
            $leftTree.find('a.jstree-anchor').each(function () {
                let a = $(this);
                let offsetleft = a.offset().left;
                a.width(treeWidth - offsetleft);
            });
        }).jstree({
            "state": {"key": "leftTree"},
            'core': {
                'check_callback': true,
                'expand_selected_onload': true,
                "open_parents": true,
                'data': data,
                themes: {
                    'name': 'default-dark'
                }
            },
            "types": {
                "folder": {},
                "plus": {"icon": "fa fa-plus"},
                "cycle_name": {"icon": "fa fa-dot-circle-o"},
                "text": {"icon": "jstree-file"},
                "table": {"icon": "jstree-file"},
                "table_simple": App.tableTypes.simple,
                "table_version": App.tableTypes.version,
                "table_calcs": App.tableTypes.calcs,
                "table_tmp": App.tableTypes.tmp,
                "table_globcalcs": App.tableTypes.globcalcs,
                "table_cycles": App.tableTypes.cycles,
                "table_data": {"icon": "jstree-file"},
            },
            "plugins": ["types", "themes"]
        });


        $leftTree.on('select_node.jstree', function (event, d) {

            switch ((d.node.data?d.node.data.type:null) || d.node.type) {
                case 'plus':
                    let subj = d.node.id.substring(5, 10);

                    if (subj === 'calcs') {
                        let parentId = d.node.id.substring(11);
                        let cycleId = d.node.parent.substring(5);
                        (new EditPanel(1, BootstrapDialog.TYPE_DANGER, {tree_node_id: {v:parentId}, type: {v:"calcs"}})).then(function (json) {
                            if (json) window.location.reload(true);
                        })
                    }
                    else if (subj === 'table') {
                        let parentId = d.node.id.length > 10 ? d.node.id.substring(10) : window.location.pathname.match(/^\/.*\/(\d+)\//)[1];
                        (new EditPanel(1, BootstrapDialog.TYPE_DANGER, {tree_node_id: {v:parentId}})).then(function (json) {
                            if (json) window.location.reload(true);
                        })
                    } else {
                        let parentId = d.node.id.length > 11 ? d.node.id.substring(11) : window.location.pathname.match(/^\/.*\/(\d+)\//)[1];
                        (new EditPanel(3, BootstrapDialog.TYPE_DANGER, {parent_id: {v:parentId}})).then(function (json) {
                            if (json) window.location.reload(true);
                        })
                    }
                    return false;
                break;
                case 'link':
                    window.location.href =  d.node.a_attr.href;
                case 'folder':
                case 'project':
                    if (d.node.state.opened) {
                        $('#leftTree').jstree('close_node', d.node);
                        if (treeStorage[d.node.id]) {
                            delete treeStorage[d.node.id]
                            localStorage.setItem('tree', JSON.stringify(treeStorage))
                        }
                    }
                    else {
                        $('#leftTree').jstree('open_node', d.node);
                        treeStorage[d.node.id] = true;
                        localStorage.setItem('tree', JSON.stringify(treeStorage))
                    }
                    return false;
                    break;
                default:
                    if (d.node.original.parent == '#')
                        window.location.href =  d.node.original.href;
                    else
                        window.location.href = $.jstree.link_prefix + d.node.original.href;
            }
            return false;
        });
        $leftTree.on('open_node.jstree', function (event, d) {
            treeStorage[d.node.id] = true;
            localStorage.setItem('tree', JSON.stringify(treeStorage))
        });
        $leftTree.on('close_node.jstree', function (event, d) {
            if (treeStorage[d.node.id]) {
                delete treeStorage[d.node.id];
                localStorage.setItem('tree', JSON.stringify(treeStorage))
            }
        });



        let TreeMinimizerStorage = localStorage.getItem('TreeMinimizer') || "false";
        TreeMinimizerStorage = JSON.parse(TreeMinimizerStorage);

        let changeTreeSize = function ($setMini) {
            TreeMinimizerStorage = $setMini;

            if ($setMini) {
                $('body>.page_content').addClass('tree-minifyed');
                $('#LeftTree').getNiceScroll().resize();
            } else {
                $('body>.page_content').removeClass('tree-minifyed');
            }
            if ($('#table').data('pctable')) {
                $('#table').data('pctable').setWidthes();
            }
            localStorage.setItem('TreeMinimizer', JSON.stringify(TreeMinimizerStorage));
            $('#LeftTree').getNiceScroll().resize();
        };

        $('#LeftTree').niceScroll({
            cursorwidth: 7,
            mousescrollstep: 190,
            mousescroll: 190,
            autohidemode: false,
            enablekeyboard: false,
            cursoropacitymin: 1,
            railoffset: {left: 4}
        });

        $('#TreeMaximizer').on('click', function () {
            changeTreeSize(false);
        });
        $('#TreeMinimizer').on('click', function () {
            changeTreeSize(true);
        });

        if (TreeMinimizerStorage === true) {
            changeTreeSize(TreeMinimizerStorage);
        }

    }

})();