<?php

/**
 * Class NoteMS
 * 用于与 note.ms 网络剪切板进行交互的工具类.
 * 提供了读取、替换保存和追加保存内容的功能.
 */
class NoteMS
{
    private const BASE_URL = 'https://note.ms';
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:142.0) Gecko/20100101 Firefox/142.0';

    private string $noteId;
    private string $url;

    /**
     * 构造函数.
     * @param string $noteId 剪切板的ID (例如 'happyyy').
     */
    public function __construct(string $noteId)
    {
        $this->noteId = $noteId;
        $this->url = self::BASE_URL . '/' . $this->noteId;
    }

    /**
     * 从 note.ms 读取内容.
     *
     * @return string|null 成功时返回内容的字符串，失败时返回 null.
     */
    public function get(): ?string
    {
        echo "[*] 正在从 '{$this->url}' 读取内容...\n";

        $ch = $this->createCurlHandle($this->url);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "[!] 读取失败，网络请求错误: " . curl_error($ch) . "\n";
            curl_close($ch);
            return null;
        }
        curl_close($ch);

        // 使用正则表达式解析内容
        if (preg_match('/<textarea class="content">(.*?)<\/textarea>/s', $response, $matches)) {
            echo "[+] 读取成功。\n";
            return html_entity_decode($matches[1]);
        }
        
        echo "[!] 读取失败: 在页面上未找到内容区域。\n";
        return null;
    }

    /**
     * 保存内容到 note.ms (支持替换和追加模式).
     *
     * @param string $content 要保存的新内容.
     * @param bool $append 是否为追加模式. true: 追加, false: 替换. 默认为 false.
     * @param string $separator 在追加模式下，用于连接原始内容和新内容的分隔符. 默认为换行符.
     * @return bool true表示成功，false表示失败.
     */
    public function save(string $content, bool $append = false, string $separator = "\n"): bool
    {
        $finalContent = $content;

        // 如果是追加模式，先获取原始内容
        if ($append) {
            echo "[*] 追加模式已开启。正在获取原始内容...\n";
            $originalContent = $this->get();

            if ($originalContent !== null) {
                // 如果原始内容不为空，则拼接内容
                if (!empty($originalContent)) {
                    $finalContent = $originalContent . $separator . $content;
                    echo "[+] 原始内容获取成功，将进行拼接。\n";
                } else {
                     echo "[+] 原始内容为空，直接写入新内容。\n";
                }
            } else {
                echo "[!] 无法获取原始内容，将终止保存操作。\n";
                return false;
            }
        }

        echo "[*] 正在向 '{$this->url}' 保存最终内容...\n";
        $postData = http_build_query(['t' => $finalContent]);

        $ch = $this->createCurlHandle($this->url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        
        curl_exec($ch);

        if (curl_errno($ch)) {
            echo "[!] 保存失败，网络请求错误: " . curl_error($ch) . "\n";
            curl_close($ch);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            echo "[+] 保存成功 (服务器返回HTTP状态码: {$httpCode})。\n";
            return true;
        }
        
        echo "[!] 保存失败，服务器返回HTTP状态码: {$httpCode}\n";
        return false;
    }

    /**
     * 创建并配置一个 cURL 句柄.
     *
     * @param string $url 请求的URL.
     * @return \CurlHandle
     */
    private function createCurlHandle(string $url)
    {
        $ch = curl_init();
        $headers = [
            'User-Agent: ' . self::USER_AGENT,
            'Referer: ' . $url
        ];
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 跟随重定向，这能增加成功率
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 

        return $ch;
    }
}


// --- ============================ ---
// ---       主程序执行示例         ---
// --- ============================ ---

// 1. 创建一个操作 'happyyy' 剪切板的实例
$clipboard = new NoteMS('testid');


// --- 示例 A: 替换保存 ---
echo "--- 示例 A: 替换保存 ---\n";
date_default_timezone_set('Asia/Shanghai');
$currentTime = date('Y-m-d H:i:s');
$replaceContent = "这是在 {$currentTime} 通过PHP脚本【替换】的内容。";

if ($clipboard->save($replaceContent, false)) { // 第二个参数为 false 或不填
    echo "替换操作成功！\n";
    // 读取并显示结果以验证
    $content = $clipboard->get();
    echo "当前内容:\n---------------------\n{$content}\n---------------------\n\n";
} else {
    echo "替换操作失败！\n\n";
}

sleep(2); // 等待2秒，避免操作太快

// --- 示例 B: 追加保存 ---
echo "--- 示例 B: 追加保存 ---\n";
$appendContent = "这是【追加】的一行新内容。";

// 第二个参数为 true, 开启追加模式
// 第三个参数可以自定义分隔符，这里使用默认的换行符 "\n"
if ($clipboard->save($appendContent, true)) { 
    echo "追加操作成功！\n";
    // 读取并显示结果以验证
    $content = $clipboard->get();
    echo "当前内容:\n---------------------\n{$content}\n---------------------\n\n";
} else {
    echo "追加操作失败！\n\n";
}

// --- 示例 C: 使用自定义分隔符追加 ---
echo "--- 示例 C: 使用自定义分隔符追加 ---\n";
$appendContent2 = "这是用' | '分隔的追加内容。";

// 第三个参数传入自定义分隔符
if ($clipboard->save($appendContent2, true, ' | ')) { 
    echo "自定义分隔符追加操作成功！\n";
    // 读取并显示结果以验证
    $content = $clipboard->get();
    echo "当前内容:\n---------------------\n{$content}\n---------------------\n\n";
} else {
    echo "自定义分隔符追加操作失败！\n\n";
}

?>