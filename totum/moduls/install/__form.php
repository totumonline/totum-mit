<div class="page_content">
    <style>
        h3 {
            padding-top: 20px;
        }

        small {
            font-size: 12px;
        }

        .panel-body {
            white-space: pre-line;
        }

        #form[data-consol="1"] #consol-scripts {
            display: none;
        }
    </style><?php
    $post = [];
    foreach ($_POST as $k => $val) {
        $post[$k] = strip_tags($val);
    }
    $post['lang'] = $post['lang'] ?? 'eng';
    $post['multy'] = $post['multy'] ?? '0';
    $post['schema_exists'] = $post['schema_exists'] ?? '1';
    $post['psql'] = $post['psql'] ?? 'psql';
    $post['pg_dump'] = $post['pg_dump'] ?? 'pg_dump';
    $post['consol'] = $post['consol'] ?? '0';

    $post['admin_email'] = $post['admin_email'] ?? '';
    $post['user_pass'] = $post['admin_email'] ?? '';
    $post['user_login'] = $post['user_login'] ?? 'admin';
    $post['db_schema'] = $post['db_schema'] ?? 'totum';

    $post['db_host'] = $post['db_host'] ?? 'localhost';

    ?>
    <form method="post" id="form" action="/" style="width: 500px; margin: auto; padding-bottom: 100px" data-consol="0">
        <div style="padding-bottom: 40px; padding-top: 10px; font-size: 55px;"><img src="/imgs/365_100_file.png"></div>
        <div id="mark"></div>
        <div id="submit_div"><input type="submit" value="Создать конфиг и залить схему"
                                    style="color: #fff; margin-top: 20px;"
                                    class="btn btn-danger" id="submit"/></div>
    </form>
    <script>
        const form = $('#form');
        <?='let post = ' . json_encode($post, JSON_UNESCAPED_UNICODE) ?>;

        const params = [
            {
                type: 'select', id: "langSelect", name: 'lang', label: "", vals: [
                    {name: "Русский язык", val: 'ru'},
                    {name: "English", val: 'eng'},
                ]
            },
            {
                type: 'select', id: "multy", name: 'multy', label: "", vals: [
                    {name: "Single installation", val: '0'},
                    {name: "Multiple installation", val: '1'},
                ]
            },
            {type: 'input', name: 'db_schema', label: "Schema"},
            {
                type: 'select', name: 'schema_exists', label: "", vals: [
                    {name: "Deploy only in the new", val: '0'},
                    {name: "Use the existing", val: '1'},
                ]
            },
            {type: 'h3', label: "Database PostgreSQL"},
            {type: 'input', name: '', id: 'dbstring', errorid: 'dbstring_error', label: "Setup string"},
            {type: 'input', name: 'db_host', label: "Database host", required: true},
            {type: 'input', name: 'db_name', label: "Database name", required: true},
            {type: 'input', name: 'db_user_login', label: "Login", required: true},
            {type: 'input', name: 'db_user_password', label: "Password", required: true},

            {
                type: 'select', name: 'consol', label: "PostgreSql console utilities", vals: [
                    {name: "With console utilities", val: '0'},
                    {name: "Without console utilities", val: '1'},
                ]
            },
            {type: 'input', name: 'pg_dump', label: "pg_dump"},
            {type: 'input', name: 'psql', label: "psql"},
            {type: 'h3', label: "Create a user with full access"},
            {type: 'input', name: 'user_login', label: "Login", required: true},
            {type: 'input', name: 'user_pass', label: "Password", required: true},
            {type: 'input', name: 'admin_email', label: "Email for cron notifications", required: true}

        ];

        $(() => {
            const formForm = function () {
                let mark = $('#mark');
                while (mark.next().length && mark.next().attr('id') != 'submit_div') {
                    mark.next().remove();
                }
                mark = $('#mark');

                params.forEach((param) => {
                    let div;
                    switch (param.type) {
                        case 'select':
                            div = $('<div class="form-group">').insertAfter(mark);
                            if (param.label) {
                                $('<label>').text(App.translate(param.label)).appendTo(div);
                            }
                            let select = $('<select>').attr('name', param.name).addClass('form-control').appendTo(div);
                            param.vals.forEach((o) => {
                                let option = $('<option>').text(App.translate(o.name)).attr('value', o.val);
                                select.append(option)
                                if (o.val == post[param.name]) {
                                    option.prop('selected', true)
                                }
                            })
                            if (param.id) {
                                select.attr('id', param.id)
                            }
                            break;
                        case 'h3':
                            div = $('<h3>').text(App.translate(param.label)).insertAfter(mark);
                            break;
                        case 'input':
                            div = $('<div class="form-group">').insertAfter(mark);
                            if (param.label) {
                                $('<label>').text(App.translate(param.label)).appendTo(div);
                            }
                            let input = $('<input>').attr('name', param.name).addClass('form-control').val(post[param.name] || '').appendTo(div);
                            if (param.required) {
                                input.prop('required', true);
                            }
                            if (param.id) {
                                input.attr('id', param.id)
                            }
                            if (param.errorid) {
                                $('<div class="error">').attr('id', param.errorid).insertAfter(input)
                            }
                            break;
                    }
                    mark = div;
                })
            }
            formForm();

            $('#form').on('change', '#langSelect', function () {
                App.lang = App.langs[$(this).val()];
                setTimeout(formForm, 20);
            })
            $('#form').on('change', 'input,select', function () {
                let self = $(this)
                post[self.attr('name')] = self.val();
            })

        })


        $('.page_content:first').addClass('tree-minifyed')

        $('#multy').on('change', function () {
            if ($(this).val() === '0') {
                $('input[name="db_schema"]').val("public");
                $('select[name="schema_exists"]').val("1");
            } else {
                $('input[name="db_schema"]').val("new_totum");
                $('select[name="schema_exists"]').val("0");
            }
        })
        $('#form').on('change', '#dbstring', function () {
            let val = $(this).val();
            $('#dbstring_error').text('');
            let matches
            if (matches = val.match(/^postgresql:\/\/(?<USER>[^:]+):(?<PASS>[^@]+)@(?<HOST>[^\/]+)\/(?<DBNAME>.+)$/)) {

                $('input[name="db_host').val(matches.groups.HOST);
                $('input[name="db_name').val(matches.groups.DBNAME);
                $('input[name="db_user_login').val(matches.groups.USER);
                $('input[name="db_user_password').val(matches.groups.PASS);
                $(this).val('');
            } else {
                $('#dbstring_error').text('Некорректная строка');
            }

        });
        $('#form').on('change', '[name="consol"]', function () {
            let val = $(this).val();
            form.attr('data-consol', val)
            if (val === '1') {
                $('input[name="pg_dump').val('');
                $('input[name="psql').val('');
            }
        })
    </script>
</div>