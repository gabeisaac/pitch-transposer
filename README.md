# Audio Pitch Transposer

Free ShrimpWeb tool. Upload a song and shift it by up to 12 semitones, either keeping the tempo or letting the speed change with the pitch.

Live: https://shrimpweb.com/pitch/

The page is `index.html`. `process.php` uses FFmpeg’s rubberband filter. The FFmpeg path in that file is the one on the ShrimpWeb server. Point it at your own `ffmpeg` binary before running this elsewhere. Uploaded audio stays out of git.

For chord charts, use the [song transposer](https://shrimpweb.com/transpose/).
