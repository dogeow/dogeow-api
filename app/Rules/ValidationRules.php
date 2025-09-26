<?php

namespace App\Rules;

class ValidationRules
{
    /**
     * 用户相关验证规则
     */
    public static function userRegistration(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public static function userLogin(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required',
        ];
    }

    public static function userUpdate(int $userId): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $userId,
        ];
    }

    /**
     * 笔记相关验证规则
     */
    public static function noteCreation(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'content_markdown' => 'nullable|string',
            'is_draft' => 'nullable|boolean',
        ];
    }

    public static function noteUpdate(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|nullable|string',
            'content_markdown' => 'sometimes|nullable|string',
            'is_draft' => 'sometimes|boolean',
        ];
    }

    /**
     * 聊天相关验证规则
     */
    public static function chatMessage(): array
    {
        return [
            'message' => 'required|string|max:2000',
            'message_type' => 'nullable|string|in:text,system',
        ];
    }

    public static function chatRoom(): array
    {
        return [
            'name' => 'required|string|min:3|max:100',
            'description' => 'nullable|string|max:500',
        ];
    }

    /**
     * 文件上传相关验证规则
     */
    public static function imageUpload(): array
    {
        return [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // 10MB
        ];
    }

    /**
     * 分页相关验证规则
     */
    public static function pagination(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * 搜索相关验证规则
     */
    public static function search(): array
    {
        return [
            'query' => 'required|string|min:1|max:255',
            'type' => 'nullable|string|in:title,content,all',
        ];
    }

    /**
     * 获取自定义错误消息
     */
    public static function getMessages(): array
    {
        return [
            'required' => ':attribute 字段是必需的。',
            'string' => ':attribute 必须是字符串。',
            'max' => ':attribute 不能超过 :max 个字符。',
            'min' => ':attribute 至少需要 :min 个字符。',
            'email' => ':attribute 必须是有效的电子邮件地址。',
            'unique' => ':attribute 已经被使用。',
            'confirmed' => ':attribute 确认不匹配。',
            'boolean' => ':attribute 必须是布尔值。',
            'integer' => ':attribute 必须是整数。',
            'image' => ':attribute 必须是图片文件。',
            'mimes' => ':attribute 必须是以下类型之一：:values。',
            'in' => '选择的 :attribute 无效。',
        ];
    }

    /**
     * 获取自定义属性名称
     */
    public static function getAttributes(): array
    {
        return [
            'name' => '姓名',
            'email' => '邮箱',
            'password' => '密码',
            'title' => '标题',
            'content' => '内容',
            'content_markdown' => 'Markdown内容',
            'is_draft' => '草稿状态',
            'message' => '消息',
            'message_type' => '消息类型',
            'description' => '描述',
            'image' => '图片',
            'query' => '搜索关键词',
            'type' => '类型',
            'page' => '页码',
            'per_page' => '每页数量',
        ];
    }
}