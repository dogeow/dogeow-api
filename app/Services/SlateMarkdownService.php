<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SlateMarkdownService
{
    /**
     * 将Slate.js JSON转换为Markdown
     *
     * @param string $jsonContent
     * @return string
     */
    public function jsonToMarkdown(string $jsonContent): string
    {
        if (empty($jsonContent)) {
            return '';
        }

        try {
            $nodes = json_decode($jsonContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($nodes)) {
                return '';
            }
            
            $markdown = '';
            foreach ($nodes as $node) {
                $markdown .= $this->processNode($node) . "\n";
            }
            
            return trim($markdown);
        } catch (\Exception $e) {
            Log::error('JSON to Markdown conversion failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * 处理单个Slate节点
     *
     * @param array $node
     * @return string
     */
    private function processNode(array $node): string
    {
        if (!isset($node['type']) || !isset($node['children'])) {
            return '';
        }

        $type = $node['type'];
        $children = $node['children'];
        $result = '';

        switch ($type) {
            case 'paragraph':
                $result = $this->processTextNodes($children);
                break;
            case 'heading-one':
                $result = '# ' . $this->processTextNodes($children);
                break;
            case 'heading-two':
                $result = '## ' . $this->processTextNodes($children);
                break;
            case 'heading-three':
                $result = '### ' . $this->processTextNodes($children);
                break;
            case 'block-quote':
                $result = '> ' . $this->processTextNodes($children);
                break;
            case 'bulleted-list':
                $items = [];
                foreach ($children as $child) {
                    if ($child['type'] === 'list-item') {
                        $items[] = '- ' . $this->processTextNodes($child['children']);
                    }
                }
                $result = implode("\n", $items);
                break;
            case 'numbered-list':
                $items = [];
                $i = 1;
                foreach ($children as $child) {
                    if ($child['type'] === 'list-item') {
                        $items[] = $i . '. ' . $this->processTextNodes($child['children']);
                        $i++;
                    }
                }
                $result = implode("\n", $items);
                break;
            case 'list-item':
                $result = $this->processTextNodes($children);
                break;
            case 'code-block':
                $language = $node['language'] ?? '';
                $code = $this->processTextNodes($children);
                $result = "```{$language}\n{$code}\n```";
                break;
            case 'image':
                $url = $node['url'] ?? '';
                $alt = 'image';
                $result = "![{$alt}]({$url})";
                break;
            default:
                $result = $this->processTextNodes($children);
        }

        return $result;
    }

    /**
     * 处理文本节点及其格式
     *
     * @param array $nodes
     * @return string
     */
    private function processTextNodes(array $nodes): string
    {
        $result = '';

        foreach ($nodes as $node) {
            if (isset($node['type'])) {
                // 如果是嵌套的块级元素
                $result .= $this->processNode($node);
                continue;
            }

            if (isset($node['text'])) {
                $text = $node['text'];
                
                // 应用格式
                if (!empty($node['bold'])) {
                    $text = "**{$text}**";
                }
                
                if (!empty($node['italic'])) {
                    $text = "*{$text}*";
                }
                
                if (!empty($node['code'])) {
                    $text = "`{$text}`";
                }
                
                if (!empty($node['link']) && !empty($node['url'])) {
                    $text = "[{$text}]({$node['url']})";
                }
                
                $result .= $text;
            }
        }

        return $result;
    }
} 