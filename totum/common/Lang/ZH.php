<?php

namespace totum\common\Lang;

use DateTime;

class ZH implements LangInterface
{
    use TranslateTrait;
    use SearchTrait;

    public const TRANSLATES = [
        'Deleting' =>'删除',
        'Not found: %s' =>'未找到：%s',
        'User not found' =>'未找到用户',
        'Not found [[%s]] for the [[%s]] parameter.' =>'未找到 [[%s]] 参数的 [[%s]]。',
        'Template not found.' =>'找不到模板。',
        'No [[%s]] is specified for the [[%s]] parameter.' =>'没有为 [[%s]] 参数指定 [[%s]]。',
        'Parametr [[%s]] is required in [[%s]] function.' =>'[[%s]] 函数中需要参数 [[%s]]。',

        'The function is only available for the Creator role.' =>'该功能仅适用于 Creator 角色。',
        'The function is not available to you.' =>'您无法使用该功能。',
        'Password doesn\'t match.' =>'密码不匹配。',
        'Scheme string is empty.' =>'方案(Scheme )字符串为空。',
        'The function is only available for cycles tables.' =>'该功能仅适用于循环表。',
        'Using a comparison type in a filter of list/row is not allowed' =>'不允许在列表/行的过滤器中使用比较类型',
        'Using a comparison type in a search in list/row is not allowed' =>'不允许在列表/行的搜索中使用比较类型',

        'Not correct field name in query to [[%s]] table.' =>'查询 [[%s]] 表中的字段名称不正确。',

        'You see the contents of the table calculated and saved before the last transaction with the error.' =>'您会看到在最后一个有错误的事务之前计算并保存的表的内容。',
        'System error. Action type not specified.' =>'系统错误,未指定操作类型。',
        'Field [[%s]] of table [[%s]] in row with id [[%s]] contains non-numeric data' =>'字段 [[%s]] 在表 [[%s]] 的id为 [[%s]] 行中包含非数字数据',
        'Scheme source not defined.' =>'未定义方案来源。',
        'Fill in the parameter [[%s]].' =>'填写参数 [[%s]] .',
        'Parametr [[%s]] is required.' =>'参数 [[%s]] 是必需的。',
        'Each button must contain [[%s]].' =>'每个按钮必须包含 [[%s]]。',
        'The parameter [[%s]] should be of type row/list.' =>'参数 [[%s]] 应该是行/列表类型。',
        'The parameter [[%s]] should be of type true/false.' =>'参数 [[%s]] 应该是 true/false 类型。',
        'The parameter [[%s]] should [[not]] be of type row/list.' =>'参数 [[%s]] 应该 [[not]] 是行/列表类型。',
        'The parameter [[%s]] should be of type string.' =>'参数 [[%s]] 应该是字符串类型。',
        'The cycles table is specified incorrectly.' =>'循环表指定不正确。',

        'Language %s not found.' =>'找不到语言 %s .',
        'You cannot create query to PostgreSql with 65000 and more parameters.' =>'PostgreSql 的查询参数不能 ≥ 65000',

        'For temporary tables only.' =>'仅适用于临时表。',
        'For simple and cycles tables only.' =>'仅适用于简单和循环表。',
        'The table has no n-sorting.' =>'该表没有 n 排序。',
        'The table [[%s]] has no n-sorting.' =>'表 [[%s]] 没有 n 排序。',
        'The %s should be here.' =>'%s 应该在这里。',

        'Parametr [[%s]] is required and should be a number.' =>'参数 [[%s]] 是必需的,应该是一个数字。',
        'Parametr [[%s]] is required and should be a string.' =>'参数 [[%s]] 是必需的,应该是一个字符串。',
        'The %s parameter is required and must start with %s.' =>'%s 参数是必需的,并且必须以 %s 开头。',
        'The %s parameter should not be an array.' =>'%s 参数不应是数组。',
        'The value of the number field should not be an array.' =>'number 字段的值不应是数组。',
        'The %s parameter must be a number.' =>'%s 参数必须是数字。',
        'The value of key %s is not a number.' =>'键 %s 的值不是数字。',


        'The [[%s]] parameter is not correct.' =>'[[%s]] 参数不正确。',
        'Comment field contains incorrect type data as a value.' => '注释字段包含错误类型的数据作为值.',

        'The [[%s]] parameter must be plain row/list without nested row/list.' => '[[%s]] 必须是没有嵌套行/列表 的简单行/列表.',



        'Calling a third-party script.' =>'调用第三方脚本。',
        'Not for the temporary table.' =>'不适用于临时表。',
        'The [[%s]] field is not found in the [[%s]] table.' =>'字段 [[%s]] 未放入表 [[%s]]。',
        'Function [[linkToEdit]] not available for [[%s]] field type.' =>'函数 [[linkToEdit]] 不适用于 [[%s]] 字段类型。',
        'The %s field must be numeric.' =>'%s 字段必须是数字。',
        'The value of the %s field must be numeric.' =>'%s 字段的值必须是数字。',
        'For selecting by numeric field [[%s]] you must pass numeric values' =>'要按数字字段 [[%s]] 进行选择,您必须传递数值',


        'The value of %s field must match the format: %s' =>'%s 字段的值必须与格式匹配：%s',
        'The row with %s was not found in table %s.' =>'在表 %s 中找不到带有 %s 的行。',
        'Row not found' =>'未找到行',
        'Row %s not found' =>'找不到字符串 %s',
        'The row %s does not exist or is not available for your role.' =>'字符串 %s 不存在或不适用于您的角色。',


        'For lists comparisons, only available =, ==, !=, !==.' =>'对于列表比较,仅可用 =、==、!=、!==。',
        'There should be a date, not a list.' =>'应该有一个日期,而不是一个列表。',
        'There must be only one comparison operator in the string.' =>'字符串中必须只有一个比较运算符。',
        'TOTUM-code format error [[%s]].' =>'TOTUM 代码格式错误 [[%s]]。',
        'XML Format Error.' =>'XML 格式错误。',
        'Code format error - no start section.' =>'代码格式错误 - 没有开始部分。',
        'The [[catch]] code of line [[%s]] was not found.' =>'未找到第 [[%s]] 行的 [[catch]] 代码。',
        'ERR!' =>'错误!',
        'Database error: [[%s]]' =>'数据库错误：[[%s]]',
        'Database connect error. Try later. [[%s]]' =>'数据库连接错误。稍后再试。[[%s]]',
        'Critical error while processing [[%s]] code.' =>'处理 [[%s]] 代码时出现严重错误。',
        'field [[%s]] of [[%s]] table' =>'字段 [[%s]] 于表 [[%s]] 中',
        'Error: %s' =>'错误: %s',
        'You cannot use linktoDataTable outside of actionCode without hide:true.' =>'不能在没有 hide:true 的情况下在 actionCode 之外使用 linktoDataTable。',


        'left element' =>'左元素',
        'right element' =>'右元素',
        'Division by zero.' =>'被零除。',
        'Unknown operator [[%s]].' =>'未知运算符 [[%s]]。',
        'Non-numeric parameter in the list %s' =>'列表 %s 中的非数字参数',
        'The [[%s]] parameter must be set to one of the following values: %s' =>'[[%s]] 参数必须设置为下列值之一：%s',
        'Function [[%s]]' =>'功能 [[%s]]',
        'Function [[%s]] is not found.' =>'未找到函数 [[%s]]。',
        'Table [[%s]] is not found.' =>'未找到表 [[%s]]。',
        'Table is not found.' =>'找不到表。',
        'File [[%s]] is not found.' =>'未找到文件 [[%s]]。',
        'Cycle [[%s]] is not found.' =>'未找到循环 [[%s]]。',
        'Cycle [[%s]] in table [[%s]] is not found.' =>'未找到表 [[%s]] 中的循环 [[%s]]。',
        'TOTUM-code format error: missing operator in expression [[%s]].' =>'TOTUM-code格式错误：表达式 [[%s]] 中缺少运算符。',

        'No key %s was found in the data row.' =>'在数据行中找不到key %s。',
        'There is no [[%s]] key in the [[%s]] list.' =>'key [[%s]] 不存在于列表 [[%s]] 中。',
        'Regular expression error: [[%s]]' =>'正则表达式错误：[[%s]]',
        'Parameter [[%s]] returned a non-true/false value.' =>'[[%s]] 参数未返回真/假。',
        'The [[%s]] parameter must contain 2 elements.' =>'[[%s]] 参数必须包含 2 个元素。',
        'The [[%s]] parameter must contain 3 elements.' =>'[[%s]] 参数必须包含 3 个元素。',
        'The %s parameter must contain a comparison element.' =>'%s 参数必须包含比较元素。',

        'Variable [[%s]] is not defined.' =>'未定义变量 [[%s]]。',
        'Code [[%s]] was not found.' =>'未找到代码 [[%s]]。',
        'Code line [[%s]].' =>'代码行 [[%s]]。',
        'Previous row not found. Works only for calculation tables.' =>'没有找到上一行。仅适用于计算表。',
        'Cannot access the current value of the field from the Code.' =>'无法从代码访问字段的当前值。',
        'Field [[%s]] is not found.' =>'未找到字段 [[%s]]。',
        'The key [[%s]] is not found in one of the array elements.' =>'在数组元素之一中找不到键 [[%s]]。',
        'There must be two [[%s]] parameters in the [[%s]] function.' =>'[[%s]] 函数中必须有两个 [[%s]] 参数。',
        'The [[%s]] parameter must be [[%s]].' =>'[[%s]] 参数必须是 [[%s]]。',
        'The [[%s]] parameter must [[not]] be [[%s]].' =>'[[%s]] 参数必须 [[not]] 为 [[%s]]。',
        'The number of the [[%s]] must be equal to the number of [[%s]].' =>'[[%s]] 的数量必须等于 [[%s]] 的数量。',
        'The [[%s]] parameter must be one type with [[%s]] parameter.' =>'[[%s]] 参数必须是具有 [[%s]] 参数的一种类型。',

        'No characters selected for generation.' =>'没有选择要生成的字符。',
        'For selecting by %s field should be passed only single value or list, not row' =>'按 %s 字段进行选择,应仅传递单个值或列表,而不是行',

        'The value by %s key is not a row/list' =>'%s 键的值不是行/列表',

        'There is no NowField enabled in this type of code. We\'ll fix it - write us.' =>'在这种类型的代码中没有启用 NowField。我们会解决它 - 写信给我们。',
        '[[%s]] is available only for the calculation table in the cycle.' =>'[[%s]] 仅适用于循环中的计算表。',

        'The ExecSSH function is disabled. Enable execSSHOn in Conf.php.' =>'ExecSSH 功能被禁用。在 Conf.php 中连接 execSSHOn',
        'Ssh:true in exec function is disabled. Enable execSSHOn in Conf.php.' =>'ssh:true 选项被禁用。在 Conf.php 中连接 execSSHOn',
        'The [[%s]] parameter has not been set in this code.' =>'此代码中未设置 [[%s]] 参数。',
        'All list elements must be lists.' =>'所有列表元素必须是列表。',
        'None of the elements of the %s parameter array must be a list.' =>'参数 %s 的所有数组元素都不能是列表。',

        'The array element does not fit the filtering conditions - the value is not a list.' =>'数组元素与过滤条件不匹配 - 值不是列表。',
        'The array element does not fit the filtering conditions - [[item]] is not found.' =>'数组元素与过滤条件不匹配 - 未找到 [[item]]。',
        '[[%s]] is not a multiple parameter.' =>'[[%s]] 不是多重参数。',
        'Not found template [[%s]] for parameter [[%s]].' =>'未找到参数 [[%s]] 的模板 [[%s]]。',
        'No template is specified for [[%s]].' =>'没有为参数 [[%s]] 提供模板。',
        'The unpaired closing parenthesis.' =>'不成对的右括号。',
        'JSON generation error: [[%s]].' =>'JSON 生成错误：[[%s]]。',
        'JSON parsing error: [[%s]].' =>'JSON 解析错误：[[%s]]。',
        'The code should return [[%s]].' =>'代码应返回 [[%s]]。',
        'The [[insert]] field should return list - Table [[%s]]' =>'字段 [[insert]] 应返回列表 - 表 [[%s]]',
        'The [[insert]] field should return a list with unique values - Table [[%s]]' =>'字段 [[insert]] 必须返回具有唯一值的列表 - 表 [[%s]]',
        'This value is not available for entry in field %s.' =>'此值不可用于字段 %s 中的输入。',

        'Format sections' =>'格式化部分',

        'Cron error' =>'Cron 错误',
        'The schema is not connected.' =>'架构未连接。',
        'Error accessing the anonymous tables module.' =>'访问匿名表模块时出错。',

        'Page processing time: %s sec.<br/>RAM: %sM. of %s.<br/>Sql Schema: %s, V %s<br/>.' => '页面处理时间: %s 秒.<br/>内存: %sM. of %s.<br/>Sql 架构: %s, V %s<br/>',

        'Order field calculation errors' => '字段计算顺序错误',
        'in %s table in fields:' => '在表 %s 的字段中: ',

        'Settings for sending mail are not set.' =>'未设置发送邮件的设置。',
        'The path to ssh script %s is not set.' =>'未设置 ssh 脚本 %s 的路径。',

        'Request processing error.' =>'请求处理错误。',
        'Error generating JSON response to client [[%s]].' =>'在客户端 [[%s]] 上生成 JSON 响应时出错。',

        'Initialization error: [[%s]].' =>'初始化失败：[[%s]]。',
        'Header' =>'标题',
        'Footer' =>'页脚',
        'Rows part' =>'内联部分',
        'Filters' =>'过滤器',
        'Filter' =>'筛选',
        'Row: id %s' =>'字符串：id %s',
        'ID is empty' =>'身份证为空',

        'User %s is not configured. Contact your system administrator.' =>'未配置用户 %s。请联系您的系统管理员。',
        'Table [[%s]] was changed. Update the table to make the changes.' =>'表 [[%s]] 已被修改。刷新表格以应用更改。',
        'Table was changed' =>'表已更改',
        'The anchor field settings are incorrect.' =>'锚字段设置不正确。',
        'Field type is not defined.' =>'未定义字段类型。',
        'Table type is not defined.' =>'未定义表类型。',
        'The [[%s]] table type is not connected to the system.' =>'表类型 [[%s]] 未连接到系统。',
        'Unsupported channel [[%s]] is specified.' =>'指定了不受支持的频道 [[%s]]。',
        'Field [[%s]] of table [[%s]] is required.' =>'[[%s]] 表的 [[%s]] 字段是必需的。',
        'Authorization lost.' =>'失去授权。',
        'Scheme file not found.' =>'未找到架构文件。',
        'Scheme not found.' =>'未找到架构。',
        'Scheme file is empty' => '示意图文件是空的',
        'Wrong format scheme file.' =>'架构文件的格式不正确。',

        'Translates file not found.' => '找不到翻译文件.',
        'Translates file is empty' => '翻译文件为空',
        'Wrong format file' =>'文件格式错误',

        'Administrator' =>'管理员',
        'The type of the loaded table [[%s]] does not match.' =>'加载的表类型不匹配 [[%s]]。',
        'The cycles table for the adding calculation table [[%s]] is not set.' =>'未为添加的计算表 [[%s]] 指定循环表。',
        'The format of the schema name is incorrect. Small English letters, numbers and - _' =>'架构名称格式不正确。小写英文字母、数字和 - _',
        'A scheme exists - choose another one to install.' =>'架构存在 - 选择另一个进行安装。',
        'You can\'t install totum in schema "public"' => '无法将 Totum 设置为公共架构',
        'Category [[%s]] not found for replacement.' =>'找不到要替换的类别 [[%s]]。',
        'Role [[%s]] not found for replacement.' =>'找不到要替换的类别 [[%s]]。',
        'Branch [[%s]] not found for replacement.' =>'找不到要替换的分支 [[%s]]。',
        'Error saving file %s' =>'保存文件 %s 时出错',
        'A nonexistent [[%s]] property was requested.' =>'请求了一个不存在的属性 [[%s]]。',
        'Import from csv is not available for [[%s]] field.' =>'从 csv 导入不适用于字段 [[%s]]。',
        'Export via csv is not available for [[%s]] field.' =>'字段 [[%s]] 无法通过 csv 导出。',
        'You do not have access to csv-import in this table' =>'您无权访问此表上的 csv 导入',


        'Date format error: [[%s]].' =>'日期格式不正确：[[%s]]。',
        '[[%s]] format error: [[%s]].' =>'[[%s]] 格式无效：[[%s]]。',
        '[[%s]] is reqired.' =>'[[%s]] 是必需的。',
        'Settings field.' =>'设置字段。',
        'You cannot create a [[footer]] field for [[non-calculated]] tables.' =>'无法创建 [[footer]] 字段 [[not for calculation]] 表。',
        'File > ' =>'档案更多',
        'File not received. May be too big.' =>'未收到文件。也许太大了。',
        'The data format is not correct for the File field.' =>'数据格式不适用于文件字段。',
        'File name search error.' =>'查找文件名时出错。',
        'The file must have an extension.' =>'该文件必须具有扩展名 .',
        'Restricted to add executable files to the server.' =>'禁止在服务器上添加可执行文件。',
        'Failed to copy a temporary file.' =>'无法复制临时文件。',
        'Failed to copy preview.' =>'无法复制预览。',
        'Error copying a file to the storage folder.' =>'将文件复制到存储文件夹时出错。',
        'Changed' =>'改变了',
        'Empty' =>'空的',
        'All' =>'一切',
        'Nothing' =>'没有',
        ' elem.' =>' 元素。',
        'Operation [[%s]] over lists is not supported.' =>'不支持工作表上的 [[%s]] 操作。',
        'Operation [[%s]] over not mupliple select is not supported.' =>'不支持对非多选的 [[%s]] 操作。',
        'Text modified' =>'文字已更改',
        'Text unchanged' =>'文本匹配',
        'The looped tree' =>'循环树',
        'Value not found' =>'未找到值',
        'Value format error' =>'值格式错误',
        'Multiselect instead of select' =>'多选而不是选择',
        'The value must be unique. Duplication in rows: [[%s - %s]]' =>'该值必须是唯一的。重复行：[[%s - %s]]',
        'There is no default version for table %s.' =>'表 %s 没有默认版本。',
        '[[%s]] cannot be a table name.' =>'[[%s]] 不能是表名。',
        '[[%s]] cannot be a field name. Choose another name.' =>'[[%s]] 不能是名称字段。选择另一个名称。',
        'The name of the field cannot be new_field' =>'字段名称不能是 new_field',
        'Table creation error.' =>'创建表时出错。',
        'You cannot delete system tables.' =>'您不能删除系统表。',
        'You cannot delete system fields.' =>'您不能删除系统字段。',
        'The [[%s]] field is already present in the table.' =>'字段 [[%s]] 已存在于表中。',
        'The [[%s]] field is already present in the [[%s]] table.' => '字段 [[%s]] 已存在于表 [[%s]] 中.',
        'Fill in the field parameters.' =>'填写字段选项。',
        'You can\'t make a boss of someone who is in a subordinate' =>'你不能让下属的人成为老板',
        'Log is empty.' =>'日志为空。',
        'Method not specified' =>'未指定方法',
        'Method [[%s]] in this module is not defined or has admin level access.' =>'此模块中的方法 [[%s]] 未定义或具有管理员级别访问权限。',
        'Method [[%s]] in this module is not defined.' =>'此模块中的方法 [[%s]] 未定义。',
        'Your access to this table is read-only. Contact administrator to make changes.' =>'您对该表的访问是只读的。联系管理员进行更改。',
        'Access to the table is denied.' =>'对表的访问被拒绝。',
        'Access to the cycle is denied.' =>'拒绝访问循环。',
        'Access via module for temporary tables only' =>'仅通过模块访问临时表',
        'Table access error' =>'表访问错误',
        'Wrong path to the table' =>'无效的表格路径',
        'Write access to the table is denied' =>'对表的写访问被拒绝',
        'Login/Email' =>'登录/电子邮件',
        'Log in' =>'登录',
        'Logout' =>'退出',
        'Send new password to email' =>'将新密码发送到电子邮件',
        'Service is optimized for desktop browsers Chrome, Safari, Yandex latest versions. It seems that your version of the browser is not supported. Error - for developers: '=> '该服务针对最新版本的 Chrome、Safari、Yandex 桌面浏览器进行了优化。 您的浏览器版本似乎不受支持。 错误 - 对于开发人员: ',
        'Credentials in %s' =>'%s 中的凭据',
        'Fill in the Login/Email field' =>'填写登录/电子邮件字段',
        'Fill in the %s field' =>'填写 %s 字段',
        'Fill in the Password field' =>'填写密码字段',
        'Password is not correct' =>'密码不正确',
        'Due to exceeding the number of password attempts, your IP is blocked' =>'由于超过密码尝试次数,您的IP被封锁',
        'Password recovery via email is disabled for this database. Contact the solution administrator.' =>'此数据库禁用通过电子邮件恢复密码。请联系解决方案管理员。',
        'Email for this login is not set' =>'未设置此登录的电子邮件',
        'Password' =>'密码',
        'An email with a new password has been sent to your Email. Check your inbox in a couple of minutes.' =>'一封带有新密码的电子邮件已发送到您的电子邮箱。几分钟后检查您的收件箱。',
        'Letter has not been sent: %s' =>'信尚未发送：%s',
        'The user with the specified Login/Email was not found' =>'未找到具有指定登录名/电子邮件的用户',
        'To work with the system you need to enable JavaScript in your browser settings' =>'要使用系统,您需要在浏览器设置中启用 JavaScript',
        'It didn\'t load :(' =>'它没有加载:(',
        'Forms user authorization error' =>'表单用户授权错误',
        'Conflicts of access to the table error' =>'访问表错误的冲突',
        'Form configuration error - user denied access to the table' =>'表单配置错误 - 用户拒绝访问表',
        'The [[%s]] field was not found. The table structure may have changed. Reload the page.'=> '未找到字段 [[%s]]。 也许表的结构发生了变化。 重新加载页面',
        'Conf.php was created successfully. Connection to the database is set up, the start scheme is installed. You are authorized under specified login with the role of Creator. Click the link or refresh the page.'=> 'Conf.php 创建成功。 配置了与数据库的连接，安装了启动方案。 您在指定的登录名下获得了 Creator 角色的授权。 点击链接或刷新页面.',
        'Have a successful use of the system' => '成功使用系统',


        'Json not received or incorrectly formatted' =>'未收到 Json 或格式不正确',
        'A database transaction was closed before the main process was completed.' =>'在主进程完成之前关闭了一个数据库事务。',
        'No auth section found' =>'未找到身份验证部分',
        'The login attribute of the auth section was not found' =>'未找到 auth 部分的登录属性',
        'The password attribute of the auth section was not found' =>'未找到 auth 部分的密码属性',
        'The user with this data was not found. Possibly the xml/json interface is not enabled.' =>'未找到拥有此数据的用户。可能没有启用 xml/json 接口。',
        'The recalculate section must contain restrictions in the format [["field":FIELDNAME,"operator":OPERATOR,"value":VALUE]]' =>'重新计算部分必须包含格式为 [["field":FIELDNAME,"operator":OPE​​RATOR,"value":VALUE]] 的限制',
        'The field is not allowed to be edited through the api or does not exist in the specified category' =>'字段不允许通过api编辑或不存在于指定类别',
        'Multiple/Single value type error' =>'多/单值类型错误',
        'In the export section, specify "fields":[] - enumeration of fields to be exported' =>'在导出部分,指定 "fields":[] - 枚举要导出的字段',
        'Incorrect where in the rows-set-where section' =>'rows-set-where 部分中的 where 不正确',
        'Without a table in the path, only the remotes section works' =>'路径中没有表格,只有遥控器部分有效',
        'Remote {var} does not exist or is not available to you' =>'远程 {var} 不存在或对您不可用',
        'The name for remote is not set' =>'未设置远程名称',
        'Field [[%s]] is not allowed to be added via Api' =>'不允许通过Api添加字段[[%s]]',
        'Field [[%s]] is not allowed to be edited via Api' =>'字段 [[%s]] 不允许通过 Api 编辑',
        'The [[%s]] field must contain multiple select' =>'[[%s]] 字段必须包含多选',
        'The [[%s]] field must contain a string' =>'[[%s]] 字段必须包含字符串',
        'The %s field in %s table does not exist' =>'表 %s 中的 %s 字段不存在',
        'You are not allowed to add to this table' =>'您无权添加到此表',
        'You are not allowed to delete from this table' =>'您无权从此表中删除',
        'You are not allowed to sort in this table' =>'您不允许在此表中排序',
        'You are not allowed to duplicate in this table' =>'您不允许在此表中重复',
        'You are not allowed to restore in this table' =>'您不允许在此表中恢复',
        'Authorization error' =>'授权错误',
        'Remote is not connected to the user' =>'遥控器未连接到用户',
        'Remote is not active or does not exist' =>'远程未激活或不存在',
        'Description' =>'描述',
        'Choose a table' =>'选择表',
        'The choice is outdated.' =>'建议的选择已过时。',
        'The proposed input is outdated.' =>'建议的输入已过时。',
        'Notifications' =>'通知',
        'Changing the name of a field' =>'更改名称字段',
        'Fill in title' =>'填写标题',
        'Select fields' =>'选择字段',
        'Csv download of this table is not allowed for your role.' =>'您无权下载此表的 CSV 文件。',
        'The name of the field is not set.' =>'未设置字段名称。',
        'Access to the field is denied' =>'字段访问被拒绝',
        'Access to edit %s field is denied' =>'访问编辑 %s 字段被拒绝',
        'Interface Error' =>'接口错误',
        'Temporary table storage time has expired' =>'临时表存储时间已过期',
        'Field not of type select/tree' =>'非选择/树类型的字段',
        'Field not of type comments' =>'非注释类型的字段',
        'The tree index is not passed' =>'树索引未通过',
        'Access to the logs is denied' =>'拒绝访问日志',
        'No manual changes were made in the field' =>'没有对字段进行手动更改',
        'Failed to get branch Id' =>'获取分支 ID 时出错',
        'Add row out of date' =>'添加行已弃用',
        'Log of manual changes by field "%s"' =>'字段“%s”的手动更改日志',
        'Calculating the table' =>'表计算',
        'Table is empty' =>'表为空',
        'Table %s. DUPLICATION CODE' =>'表 %s。复制时的代码',
        'Incorrect encoding of the file (should be utf-8 or windows-1251)' =>'文件编码无效（应为 utf-8 或 windows-1251）',
        'Loading file of table %s into table [[%s]]' =>'将表文件 %s 加载到表 [[%s]]',
        'in row %s' =>'在第 %s 行',
        'no table change code' =>'没有更改表代码',
        'no structure change code' =>'没有更改结构代码',
        'The structure of the table was changed. Possibly a field order mismatch.' =>'表的结构已更改。字段顺序可能不匹配。',
        'no indication of a cycle' =>'缺少循环',
        'Table from another cycle or out of cycles' =>'来自另一个周期或周期外的表格',
        'Out of cycles' =>'超出周期',
        'Manual Values' =>'手动值',
        'there is no Manual Values section header' =>'缺少节标题手动值',
        'no 0/1/2 edit switch' =>'缺少 0/1/2 编辑开关',
        'no section header %s' =>'缺少节标题 %s',
        'no filter data' =>'没有过滤数据',
        'on the line one line after the Rows part is missing the header of the Footer section' =>'在内联部分之后的第一行中,缺少页脚部分的标题',
        '[0: do not modify calculated fields] [1: change values of calculated fields already set to manual] [2: change calculated fields]' =>'[0：不处理计算字段] [1：更改已设置为手动的计算字段的值] [2：更改计算字段]',

        'More than 20 nesting levels of table changes. Most likely a recalculation loop' =>'超过 20 个嵌套级别的表格更改。很可能是重新计算循环',
        'The field is not configured.' =>'字段未配置',
        'No select field specified' =>'未指定选择字段',
        'More than one field/sfield is specified' =>'指定了多个领域/领域',
        'The %s function is not provided for this type of tables' =>'%s 函数不适用于此表类型',
        'script' =>'脚本',
        'Field [[%s]] in table [[%s]] is not a column' =>'字段 [[%s]]在表 [[%s]] 中不是列',
        'In the %s parameter you must use a list by the number of rows to be changed or not a list.' =>'在 %s 参数中,您必须使用要更改的行数的列表或不使用列表。',
        'The function is used to change the rows part of the table.' =>'该函数用于更改表格的行部分。',
        'Incorrect interval [[%s]]' =>'无效间距 [[%s]]',
        'The calculation table is not connected to %s cycles table' =>'计算表未连接到循环表 %s',
        'User access' =>'用户访问',
        'Button to the cycle' =>'按钮循环',
        'First you have to delete the cycles table, and then the calculation tables inside it' =>'首先你需要删除循环表,然后是里面的计算表',
        'No line-by-line updates are provided for the calculation tables. They are recalculated in whole' =>'计算表不提供逐行更新。它们被完全重新计算。',
        'Error processing field insert: [[%s]]' =>'解析插入字段时出错：[[%s]]',
        'Open' =>'打开',
        'The row with id %s in the table already exists. Cannot be added again' =>'表中已存在 ID 为 %s 的行。无法再次添加',
        'The [[%s]] field in the rows part of table [[%s]] does not exist' =>'[[%s]] 字段不存在于表 [[%s]] 行部分',
        'Client side error. Received row instead of id' =>'客户端错误。收到行而不是 id',
        'Client side error' =>'客户端错误',
        'Logic error n: %s' =>'逻辑错误 n: %s',
        'Adding row error' =>'添加行错误',
        'The Parameters field type is valid only for the Tables Fields table' =>'Parameters 字段类型仅对 Tables Fields 表有效'



    ];
    protected const monthRods = [
        1 => '一月',
        '二月',
        '三月',
        '四月',
        '五月',
        '六月',
        '七月',
        '八月',
        '九月',
        '十月',
        '十一月',
        '十二月'
    ];
    protected const months = [
        1 => '一月',
        '二月',
        '三月',
        '四月',
        '五月',
        '六月',
        '七月',
        '八月',
        '九月',
        '十月',
        '十一月',
        '十二月'
    ];
    protected const monthsShort = [
        1 => '一月',
        '二月',
        '三月',
        '四月',
        '五月',
        '六月',
        '七月',
        '八月',
        '九月',
        '十月',
        '十一月',
        '十二月'
    ];
    protected const weekDays = [
        1 => '星期一',
        '星期二',
        '星期三',
        '星期四',
        '星期五',
        '星期六',
        '星期日'
    ];
    protected const weekDaysShort = [
        1 => '周一',
        '周二',
        '周三',
        '周四',
        '周五',
        '周六',
        '周日'
    ];

    /**
     * 返回单词的数量
     * @author runcore
     * @uses morph(...)
     */
    public function num2str($num): string
    {
        $nul = '零';
        $ten = array(
            array('', '一','二','三','四','五','六','七','八','九'),
            array('', '一','二','三','四','五','六','七','八','九'),
        );
        $a20 = array('十','十一','十二','十三','十四','十五','十六','十七','十八','十九');
        $tens = array(2 => '二十','三十','四十','五十','六十','七十','八十','九十');
        $hundred = array('', '一百', '两百', '三百', '四百', '五百', '六百', '七百', '八百', '九百');
        $unit = array( // Units
            array('分', '分', '分', 1),
            array('元', '元', '元', 0),
            array('千', '千', '千', 1),
            array('百万', '百万', '百万', 0),
            array('十亿', '十亿', '十亿', 0),
        );
        //
        list($rub, $kop) = explode('.', sprintf("%015.2f", floatval($num)));
        $out = array();
        if (intval($rub) > 0) {
            foreach (str_split($rub, 3) as $uk => $v) { // by 3 symbols
                if (!intval($v)) {
                    continue;
                }
                $uk = sizeof($unit) - $uk - 1; // unit key
                $gender = $unit[$uk][3];
                list($i1, $i2, $i3) = array_map('intval', str_split($v, 1));
                // mega-logic
                $out[] = $hundred[$i1]; # 1xx-9xx
                if ($i2 > 1) {
                    $out[] = $tens[$i2] . ' ' . $ten[$gender][$i3];
                } # 20-99
                else {
                    $out[] = $i2 > 0 ? $a20[$i3] : $ten[$gender][$i3];
                } # 10-19 | 1-9
                // units without rub & kop
                if ($uk > 1) {
                    $out[] = static::morph($v, $unit[$uk][0], $unit[$uk][1], $unit[$uk][2]);
                }
            } //foreach
        } else {
            $out[] = $nul;
        }
        $out[] = static::morph(intval($rub), $unit[1][0], $unit[1][1], $unit[1][2]); // rub
        $out[] = $kop . ' ' . static::morph($kop, $unit[0][0], $unit[0][1], $unit[0][2]); // kop
        return trim(preg_replace('/ {2,}/', ' ', join(' ', $out)));
    }

    public function smallTranslit($s): string
    {
        return strtr(
            $s,
            ['а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => '']
        );
    }

    /**
     * Склоняем словоформу
     * @ author runcore
     */
    protected static function morph($n, $f1, $f2, $f5)
    {
        $n = abs(intval($n)) % 100;
        if ($n > 10 && $n < 20) {
            return $f5;
        }
        $n = $n % 10;
        if ($n > 1 && $n < 5) {
            return $f2;
        }
        if ($n === 1) {
            return $f1;
        }
        return $f5;
    }

    public function dateFormat(DateTime $date, $fStr): string
    {
        $result = '';
        foreach (preg_split(
                     '/([DlFfM])/',
                     $fStr,
                     -1,
                     PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
                 ) as $split) {
            $var = null;
            switch ($split) {
                case 'D':
                    $var = 'weekDaysShort';
                // no break
                case 'l':
                    $var = $var ?? 'weekDays';
                    $result .= $this->getConstant($var)[$date->format('N')];
                    break;
                case 'F':
                    $var = 'months';
                // no break
                case 'f':
                    $var = $var ?? 'monthRods';
                // no break
                case 'M':
                    $var = $var ?? 'monthsShort';
                    $result .= $this->getConstant($var)[$date->format('n')];
                    break;
                default:
                    $result .= $date->format($split);
            }
        }
        return $result;
    }

    protected function getConstant($name): array
    {
        return match ($name) {
            'monthsShort' => static::monthsShort,
            'months' => static::months,
            'weekDays' => static::weekDays,
            'weekDaysShort' => static::weekDaysShort,
            'monthRods' => static::monthRods,
        };
    }
}