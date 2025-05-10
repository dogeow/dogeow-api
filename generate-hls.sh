#!/bin/bash

# HLS 音频文件生成脚本
# 用法: ./generate-hls.sh <源目录> <目标目录>

# 检查参数
if [ $# -lt 2 ]; then
    echo "用法: $0 <源目录> <目标目录>"
    echo "例如: $0 ./public/musics ./public/musics/hls"
    exit 1
fi

SOURCE_DIR="$1"
TARGET_DIR="$2"

# 检查目录是否存在
if [ ! -d "$SOURCE_DIR" ]; then
    echo "错误: 源目录 '$SOURCE_DIR' 不存在"
    exit 1
fi

# 检查 ffmpeg 是否安装
if ! command -v ffmpeg &> /dev/null; then
    echo "错误: ffmpeg 未安装，请先安装 ffmpeg"
    exit 1
fi

# 创建目标目录
mkdir -p "$TARGET_DIR"

# 用于跟踪是否有任何转换失败
conversion_failed=0

# 处理所有音频文件
find "$SOURCE_DIR" -type f \( -name "*.mp3" -o -name "*.wav" -o -name "*.m4a" \) | while read -r file; do
    # 获取文件名和扩展名
    filename=$(basename -- "$file")
    extension="${filename##*.}"
    filename_noext="${filename%.*}"
    
    # 创建目标子目录
    target_subdir="$TARGET_DIR/$filename_noext"
    mkdir -p "$target_subdir"
    
    echo "处理文件: $filename"
    echo "目标目录: $target_subdir"
    
    # 使用 FFmpeg 转换为 HLS
    ffmpeg -y -i "$file" \
        -c:a aac -b:a 192k -ac 2 \
        -hls_time 10 \
        -hls_playlist_type vod \
        -hls_segment_filename "$target_subdir/%03d.ts" \
        "$target_subdir/playlist.m3u8"
    
    # 检查转换是否成功
    if [ $? -eq 0 ]; then
        echo "✅ 成功转换: $filename -> $target_subdir/playlist.m3u8"
    else
        echo "❌ 转换失败: $filename"
        conversion_failed=1
        exit 1  # 在管道内退出，设置管道状态
    fi
    
    # 如果有封面图片，复制到目标目录
    cover_jpg="$SOURCE_DIR/${filename_noext}.jpg"
    cover_png="$SOURCE_DIR/${filename_noext}.png"
    
    if [ -f "$cover_jpg" ]; then
        cp "$cover_jpg" "$target_subdir/cover.jpg"
        echo "✅ 复制封面: $cover_jpg -> $target_subdir/cover.jpg"
    elif [ -f "$cover_png" ]; then
        cp "$cover_png" "$target_subdir/cover.png"
        echo "✅ 复制封面: $cover_png -> $target_subdir/cover.png"
    fi
    
    echo "------------------------"
done

# 检查管道状态
pipe_status=${PIPESTATUS[1]}
if [ $pipe_status -ne 0 ]; then
    echo "转换过程中出现错误，处理已中断"
    exit 1
fi

echo "处理完成! 所有文件已转换到: $TARGET_DIR"
echo "您可以通过 API 访问: /api/music/hls" 