<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatModerationAction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContentFilterService
{
    /**
     * Inappropriate words list (basic implementation)
     * In production, this should be stored in database or external service
     */
    private const INAPPROPRIATE_WORDS = [
        'spam', 'scam', 'fake', 'bot', 'hack', 'cheat',
        'stupid', 'idiot', 'moron', 'dumb', 'loser',
        'hate', 'kill', 'die', 'death', 'murder',
        'drug', 'drugs', 'cocaine', 'heroin', 'weed',
        'porn', 'sex', 'nude', 'naked', 'xxx',
        // Add more words as needed
    ];

    /**
     * Replacement words for filtered content
     */
    private const WORD_REPLACEMENTS = [
        'spam' => '****',
        'scam' => '****',
        'stupid' => '[filtered]',
        'idiot' => '[filtered]',
        'hate' => '[filtered]',
        'kill' => '[filtered]',
        'die' => '[filtered]',
        'drug' => '[filtered]',
        'drugs' => '[filtered]',
        'porn' => '[filtered]',
        'sex' => '[filtered]',
    ];

    /**
     * Spam detection thresholds
     */
    private const SPAM_MESSAGE_LIMIT = 5; // Messages per minute
    private const SPAM_DUPLICATE_LIMIT = 3; // Duplicate messages
    private const SPAM_CAPS_THRESHOLD = 0.7; // 70% caps
    private const SPAM_REPETITION_THRESHOLD = 0.5; // 50% repeated characters

    /**
     * Check if message contains inappropriate content
     *
     * @param string $message
     * @return array
     */
    public function checkInappropriateContent(string $message): array
    {
        $violations = [];
        $severity = 'low';
        $filteredMessage = $message;
        
        $lowerMessage = strtolower($message);
        
        foreach (self::INAPPROPRIATE_WORDS as $word) {
            if (strpos($lowerMessage, strtolower($word)) !== false) {
                $violations[] = [
                    'type' => 'inappropriate_word',
                    'word' => $word,
                    'severity' => $this->getWordSeverity($word)
                ];
                
                // Replace the word if replacement exists
                if (isset(self::WORD_REPLACEMENTS[$word])) {
                    $filteredMessage = str_ireplace($word, self::WORD_REPLACEMENTS[$word], $filteredMessage);
                }
                
                // Update overall severity
                $wordSeverity = $this->getWordSeverity($word);
                if ($wordSeverity === 'high' || ($wordSeverity === 'medium' && $severity === 'low')) {
                    $severity = $wordSeverity;
                }
            }
        }
        
        return [
            'has_violations' => !empty($violations),
            'violations' => $violations,
            'severity' => $severity,
            'filtered_message' => $filteredMessage,
            'action_required' => $severity === 'high' || count($violations) >= 3
        ];
    }

    /**
     * Get severity level for a specific word
     *
     * @param string $word
     * @return string
     */
    private function getWordSeverity(string $word): string
    {
        $highSeverityWords = ['hate', 'kill', 'die', 'death', 'murder', 'drug', 'drugs', 'porn', 'xxx'];
        $mediumSeverityWords = ['stupid', 'idiot', 'moron', 'dumb', 'loser', 'sex', 'nude', 'naked'];
        
        if (in_array($word, $highSeverityWords)) {
            return 'high';
        } elseif (in_array($word, $mediumSeverityWords)) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Detect spam patterns in message
     *
     * @param string $message
     * @param int $userId
     * @param int $roomId
     * @return array
     */
    public function detectSpam(string $message, int $userId, int $roomId): array
    {
        $violations = [];
        $severity = 'low';
        
        // Check message frequency
        $frequencyCheck = $this->checkMessageFrequency($userId, $roomId);
        if ($frequencyCheck['is_spam']) {
            $violations[] = [
                'type' => 'high_frequency',
                'details' => $frequencyCheck,
                'severity' => 'high'
            ];
            $severity = 'high';
        }
        
        // Check for duplicate messages
        $duplicateCheck = $this->checkDuplicateMessages($message, $userId, $roomId);
        if ($duplicateCheck['is_spam']) {
            $violations[] = [
                'type' => 'duplicate_message',
                'details' => $duplicateCheck,
                'severity' => 'medium'
            ];
            if ($severity === 'low') {
                $severity = 'medium';
            }
        }
        
        // Check for excessive caps
        $capsCheck = $this->checkExcessiveCaps($message);
        if ($capsCheck['is_spam']) {
            $violations[] = [
                'type' => 'excessive_caps',
                'details' => $capsCheck,
                'severity' => 'low'
            ];
        }
        
        // Check for character repetition
        $repetitionCheck = $this->checkCharacterRepetition($message);
        if ($repetitionCheck['is_spam']) {
            $violations[] = [
                'type' => 'character_repetition',
                'details' => $repetitionCheck,
                'severity' => 'low'
            ];
        }
        
        // Check for URL spam
        $urlCheck = $this->checkUrlSpam($message);
        if ($urlCheck['is_spam']) {
            $violations[] = [
                'type' => 'url_spam',
                'details' => $urlCheck,
                'severity' => 'medium'
            ];
            if ($severity === 'low') {
                $severity = 'medium';
            }
        }
        
        return [
            'is_spam' => !empty($violations),
            'violations' => $violations,
            'severity' => $severity,
            'action_required' => $severity === 'high' || count($violations) >= 2
        ];
    }

    /**
     * Check message frequency for spam detection
     *
     * @param int $userId
     * @param int $roomId
     * @return array
     */
    private function checkMessageFrequency(int $userId, int $roomId): array
    {
        $cacheKey = "chat_message_frequency_{$userId}_{$roomId}";
        $messages = Cache::get($cacheKey, []);
        
        // Clean old messages (older than 1 minute)
        $oneMinuteAgo = now()->subMinute()->timestamp;
        $messages = array_filter($messages, function($timestamp) use ($oneMinuteAgo) {
            return $timestamp > $oneMinuteAgo;
        });
        
        // Add current message timestamp
        $messages[] = now()->timestamp;
        
        // Store back in cache
        Cache::put($cacheKey, $messages, 300); // 5 minutes
        
        $messageCount = count($messages);
        
        return [
            'is_spam' => $messageCount > self::SPAM_MESSAGE_LIMIT,
            'message_count' => $messageCount,
            'limit' => self::SPAM_MESSAGE_LIMIT,
            'time_window' => '1 minute'
        ];
    }

    /**
     * Check for duplicate messages
     *
     * @param string $message
     * @param int $userId
     * @param int $roomId
     * @return array
     */
    private function checkDuplicateMessages(string $message, int $userId, int $roomId): array
    {
        $recentMessages = ChatMessage::where('user_id', $userId)
            ->where('room_id', $roomId)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->pluck('message')
            ->toArray();
        
        $duplicateCount = 0;
        $messageHash = md5(strtolower(trim($message)));
        
        foreach ($recentMessages as $recentMessage) {
            if (md5(strtolower(trim($recentMessage))) === $messageHash) {
                $duplicateCount++;
            }
        }
        
        return [
            'is_spam' => $duplicateCount >= self::SPAM_DUPLICATE_LIMIT,
            'duplicate_count' => $duplicateCount,
            'limit' => self::SPAM_DUPLICATE_LIMIT,
            'time_window' => '5 minutes'
        ];
    }

    /**
     * Check for excessive capital letters
     *
     * @param string $message
     * @return array
     */
    private function checkExcessiveCaps(string $message): array
    {
        $totalChars = strlen(preg_replace('/[^a-zA-Z]/', '', $message));
        
        if ($totalChars < 10) {
            return ['is_spam' => false]; // Too short to determine
        }
        
        $capsChars = strlen(preg_replace('/[^A-Z]/', '', $message));
        $capsRatio = $totalChars > 0 ? $capsChars / $totalChars : 0;
        
        return [
            'is_spam' => $capsRatio > self::SPAM_CAPS_THRESHOLD,
            'caps_ratio' => $capsRatio,
            'threshold' => self::SPAM_CAPS_THRESHOLD,
            'caps_count' => $capsChars,
            'total_letters' => $totalChars
        ];
    }

    /**
     * Check for character repetition spam
     *
     * @param string $message
     * @return array
     */
    private function checkCharacterRepetition(string $message): array
    {
        if (strlen($message) < 10) {
            return ['is_spam' => false];
        }
        
        // Count repeated character sequences
        $repetitionCount = 0;
        $totalChars = strlen($message);
        
        for ($i = 0; $i < $totalChars - 2; $i++) {
            $char = $message[$i];
            $consecutiveCount = 1;
            
            for ($j = $i + 1; $j < $totalChars && $message[$j] === $char; $j++) {
                $consecutiveCount++;
            }
            
            if ($consecutiveCount >= 4) { // 4 or more consecutive same characters
                $repetitionCount += $consecutiveCount;
                $i = $j - 1; // Skip the counted characters
            }
        }
        
        $repetitionRatio = $totalChars > 0 ? $repetitionCount / $totalChars : 0;
        
        return [
            'is_spam' => $repetitionRatio > self::SPAM_REPETITION_THRESHOLD,
            'repetition_ratio' => $repetitionRatio,
            'threshold' => self::SPAM_REPETITION_THRESHOLD,
            'repetition_count' => $repetitionCount,
            'total_chars' => $totalChars
        ];
    }

    /**
     * Check for URL spam
     *
     * @param string $message
     * @return array
     */
    private function checkUrlSpam(string $message): array
    {
        // Count URLs in message
        $urlPattern = '/https?:\/\/[^\s]+/i';
        preg_match_all($urlPattern, $message, $matches);
        $urlCount = count($matches[0]);
        
        // Check for suspicious URL patterns
        $suspiciousPatterns = [
            '/bit\.ly/i',
            '/tinyurl/i',
            '/t\.co/i',
            '/goo\.gl/i',
            '/ow\.ly/i',
            '/free.*money/i',
            '/click.*here/i',
            '/limited.*time/i',
        ];
        
        $suspiciousUrls = 0;
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                $suspiciousUrls++;
            }
        }
        
        return [
            'is_spam' => $urlCount > 2 || $suspiciousUrls > 0,
            'url_count' => $urlCount,
            'suspicious_urls' => $suspiciousUrls,
            'urls' => $matches[0]
        ];
    }

    /**
     * Process message through content filter
     *
     * @param string $message
     * @param int $userId
     * @param int $roomId
     * @return array
     */
    public function processMessage(string $message, int $userId, int $roomId): array
    {
        $result = [
            'allowed' => true,
            'filtered_message' => $message,
            'violations' => [],
            'actions_taken' => [],
            'severity' => 'none'
        ];
        
        // Check inappropriate content
        $contentCheck = $this->checkInappropriateContent($message);
        if ($contentCheck['has_violations']) {
            $result['violations']['content'] = $contentCheck;
            $result['filtered_message'] = $contentCheck['filtered_message'];
            $result['severity'] = $contentCheck['severity'];
            
            if ($contentCheck['action_required']) {
                $result['allowed'] = false;
                $result['actions_taken'][] = 'message_blocked';
                
                // Log the action
                $this->logModerationAction($roomId, $userId, null, ChatModerationAction::ACTION_CONTENT_FILTER, [
                    'original_message' => $message,
                    'violations' => $contentCheck['violations'],
                    'severity' => $contentCheck['severity']
                ]);
            }
        }
        
        // Check for spam
        $spamCheck = $this->detectSpam($message, $userId, $roomId);
        if ($spamCheck['is_spam']) {
            $result['violations']['spam'] = $spamCheck;
            
            if ($spamCheck['action_required']) {
                $result['allowed'] = false;
                $result['actions_taken'][] = 'spam_blocked';
                
                // Log the action
                $this->logModerationAction($roomId, $userId, null, ChatModerationAction::ACTION_SPAM_DETECTION, [
                    'message' => $message,
                    'violations' => $spamCheck['violations'],
                    'severity' => $spamCheck['severity']
                ]);
                
                // Auto-mute user if high severity spam
                if ($spamCheck['severity'] === 'high') {
                    $this->autoMuteUser($userId, $roomId, 'Automatic mute for spam detection');
                    $result['actions_taken'][] = 'user_auto_muted';
                }
            }
            
            // Update severity if spam is more severe
            if ($spamCheck['severity'] === 'high' || ($spamCheck['severity'] === 'medium' && $result['severity'] === 'low')) {
                $result['severity'] = $spamCheck['severity'];
            }
        }
        
        return $result;
    }

    /**
     * Auto-mute user for violations
     *
     * @param int $userId
     * @param int $roomId
     * @param string $reason
     * @param int $durationMinutes
     * @return bool
     */
    private function autoMuteUser(int $userId, int $roomId, string $reason, int $durationMinutes = 10): bool
    {
        try {
            $roomUser = \App\Models\ChatRoomUser::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->first();
            
            if ($roomUser) {
                $roomUser->update([
                    'is_muted' => true,
                    'muted_until' => now()->addMinutes($durationMinutes),
                    'muted_by' => 1 // System user
                ]);
                
                // Log the auto-mute action
                $this->logModerationAction($roomId, $userId, 1, ChatModerationAction::ACTION_MUTE_USER, [
                    'duration_minutes' => $durationMinutes,
                    'auto_action' => true,
                    'reason' => $reason
                ]);
                
                return true;
            }
        } catch (\Exception $e) {
            Log::error('Failed to auto-mute user', [
                'user_id' => $userId,
                'room_id' => $roomId,
                'error' => $e->getMessage()
            ]);
        }
        
        return false;
    }

    /**
     * Log moderation action
     *
     * @param int $roomId
     * @param int $targetUserId
     * @param int|null $moderatorId
     * @param string $actionType
     * @param array $metadata
     * @return void
     */
    private function logModerationAction(int $roomId, int $targetUserId, ?int $moderatorId, string $actionType, array $metadata = []): void
    {
        try {
            ChatModerationAction::create([
                'room_id' => $roomId,
                'moderator_id' => $moderatorId ?? 1, // System user for automated actions
                'target_user_id' => $targetUserId,
                'action_type' => $actionType,
                'reason' => $metadata['reason'] ?? 'Automated content filtering',
                'metadata' => $metadata
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log moderation action', [
                'room_id' => $roomId,
                'target_user_id' => $targetUserId,
                'action_type' => $actionType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get content filter statistics
     *
     * @param int|null $roomId
     * @param int $days
     * @return array
     */
    public function getFilterStats(?int $roomId = null, int $days = 7): array
    {
        $query = ChatModerationAction::where('created_at', '>=', now()->subDays($days))
            ->whereIn('action_type', [
                ChatModerationAction::ACTION_CONTENT_FILTER,
                ChatModerationAction::ACTION_SPAM_DETECTION
            ]);
        
        if ($roomId) {
            $query->where('room_id', $roomId);
        }
        
        $actions = $query->get();
        
        $stats = [
            'total_actions' => $actions->count(),
            'content_filter_actions' => $actions->where('action_type', ChatModerationAction::ACTION_CONTENT_FILTER)->count(),
            'spam_detection_actions' => $actions->where('action_type', ChatModerationAction::ACTION_SPAM_DETECTION)->count(),
            'severity_breakdown' => [
                'low' => 0,
                'medium' => 0,
                'high' => 0
            ],
            'top_violations' => [],
            'affected_users' => $actions->pluck('target_user_id')->unique()->count(),
            'period_days' => $days
        ];
        
        // Calculate severity breakdown
        foreach ($actions as $action) {
            $severity = $action->metadata['severity'] ?? 'low';
            if (isset($stats['severity_breakdown'][$severity])) {
                $stats['severity_breakdown'][$severity]++;
            }
        }
        
        // Get top violation types
        $violationTypes = [];
        foreach ($actions as $action) {
            if (isset($action->metadata['violations'])) {
                foreach ($action->metadata['violations'] as $violation) {
                    $type = $violation['type'] ?? 'unknown';
                    $violationTypes[$type] = ($violationTypes[$type] ?? 0) + 1;
                }
            }
        }
        
        arsort($violationTypes);
        $stats['top_violations'] = array_slice($violationTypes, 0, 10, true);
        
        return $stats;
    }
}