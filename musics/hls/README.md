# HLS 音乐播放器使用指南

这个目录用于存放 HLS 格式的音乐文件，支持全平台（PC、安卓和 iOS）播放。

## 什么是 HLS？

HLS (HTTP Live Streaming) 是由 Apple 公司开发的一种流媒体协议，它将媒体文件分割成小片段，使用 HTTP 协议传输。HLS 的优点包括：

- 支持自适应码率流
- 支持暂停、快进、快退等操作
- 支持全平台（PC、Mac、iOS、Android）
- 支持直播和点播

## 目录结构

每个歌曲应该有自己的目录，结构如下：

```
/歌曲名/
  ├── playlist.m3u8    # HLS 播放列表
  ├── 000.ts           # 音频分片 1
  ├── 001.ts           # 音频分片 2
  ├── ...              # 更多分片
  └── cover.jpg        # 可选：封面图片
```

## 如何生成 HLS 文件

### 方法 1：使用脚本批量转换

在项目根目录执行以下命令将 public/musics 目录下的所有音频文件转换为 HLS 格式：

```bash
./generate-hls.sh ./public/musics ./public/musics/hls
```

### 方法 2：使用 FFmpeg 手动转换

对于单个音频文件，可以使用以下命令转换：

```bash
mkdir -p public/musics/hls/歌曲名
ffmpeg -i "public/musics/歌曲.mp3" \
  -c:a aac -b:a 192k \
  -hls_time 10 \
  -hls_playlist_type vod \
  -hls_segment_filename "public/musics/hls/歌曲名/%03d.ts" \
  "public/musics/hls/歌曲名/playlist.m3u8"
```

### 方法 3：使用 API 转换

也可以通过 API 直接转换，例如：

```
POST /api/music/hls/generate
{
  "source": "歌曲.mp3",
  "output": "歌曲名"
}
```

## 访问 HLS 音乐

生成的 HLS 音乐可以通过以下 API 访问：

- 获取音乐列表：`GET /api/music/hls`
- 获取 M3U8 文件：`GET /api/music/hls/stream/歌曲名/playlist.m3u8`
- 获取 TS 分片：`GET /api/music/hls/stream/歌曲名/000.ts`

## 前端播放

前端使用 `HLSMusicPlayer` 组件播放 HLS 音乐，自动适配各种平台：

- PC/安卓：使用 hls.js 库播放
- iOS：使用原生 HLS 支持播放 