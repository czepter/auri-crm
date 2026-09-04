#!/bin/sh
# libvips derivative-throughput probe for the P3 gallery spike (G-00).
#
#   docker run --rm -v "$PWD/spikes:/spikes:ro" -v vipsbench:/tmp/vipsbench debian:bookworm-slim \
#     sh -c 'apt-get update -qq && apt-get install -y -qq libvips-tools curl ca-certificates >/dev/null \
#            && sh /spikes/vips-throughput.sh 24 4 24'
#
# The named volume is optional; it just keeps the generated source originals
# between runs so a concurrency sweep does not rebuild them each time.
#
# Usage: vips-throughput.sh [megapixels] [concurrency] [images]
# Builds realistic source JPEGs once, then times the thumb/web/preview
# derivative set at the requested worker concurrency. Each worker is pinned to
# VIPS_CONCURRENCY=1 because production workers are queue processes, not threads.
set -e
MP=${1:-24}; C=${2:-1}; N=${3:-12}
W=$(awk "BEGIN{printf \"%d\", sqrt($MP*1000000*1.5)}")
H=$(awk "BEGIN{printf \"%d\", $W/1.5}")
WORK=/tmp/vipsbench; SRC=$WORK/src-$MP; OUT=$WORK/out
mkdir -p "$SRC" "$OUT"

if [ ! -f "$SRC/.ready" ]; then
  echo "building 3 source originals at ${W}x${H} (~${MP} MP)…"
  i=0
  for id in photo-1519741497674-611481863552 photo-1511285560929-80b456fea0bc photo-1465495976277-4387d4b0b4c6; do
    i=$((i+1))
    [ -f "$WORK/base$i.jpg" ] || curl -sS -o "$WORK/base$i.jpg" "https://images.unsplash.com/$id?w=3000&q=85"
    # Upscale to target, then add gaussian noise back so the high-frequency
    # energy (and therefore JPEG encode cost and file size) is camera-realistic.
    vips thumbnail "$WORK/base$i.jpg" "$WORK/up.v" "$W" --height "$H" --size force
    vips gaussnoise "$WORK/noise.v" "$W" "$H" --mean 0 --sigma 6
    vips add "$WORK/up.v" "$WORK/noise.v" "$WORK/sum.v"
    vips jpegsave "$WORK/sum.v" "$SRC/o$i.jpg" --Q 92
    rm -f "$WORK/up.v" "$WORK/noise.v" "$WORK/sum.v"
  done
  touch "$SRC/.ready"
fi
echo "source originals:"; ls -l "$SRC"/*.jpg | awk '{printf "  %s  %.1f MB\n", $NF, $5/1048576}'

# N work items, round-robining the three originals.
: > "$WORK/list"
i=0; while [ $i -lt "$N" ]; do
  echo "$SRC/o$(( i % 3 + 1 )).jpg $i" >> "$WORK/list"; i=$((i+1))
done

run() {  # run <label> <pipeline>; $0 is the source path, $1 the item index
  rm -rf "$OUT"; mkdir -p "$OUT"
  start=$(date +%s.%N)
  xargs -P "$C" -n 2 sh -c "export VIPS_CONCURRENCY=1; $2" < "$WORK/list" > /dev/null || return 1
  end=$(date +%s.%N)
  kb=$(du -sk "$OUT" | cut -f1)
  awk -v s="$start" -v e="$end" -v n="$N" -v c="$C" -v l="$1" -v kb="$kb" \
    'BEGIN{d=e-s; printf "%-22s C=%-2d  %6.2f s total  %6.3f s/img  %6.2f img/s/worker  %7.2f img/s  %6.0f KB/img out\n", l, c, d, d/n, n/d/c, n/d, kb/n}'
}

D_JPEG='vipsthumbnail "$0" -s 2560 -o /tmp/vipsbench/out/$1-p.jpg[Q=85,strip] &&
        vipsthumbnail "$0" -s 1600 -o /tmp/vipsbench/out/$1-w.jpg[Q=82,strip] &&
        vipsthumbnail "$0" -s 400  -o /tmp/vipsbench/out/$1-t.jpg[Q=80,strip]'
D_WEBP='vipsthumbnail "$0" -s 2560 -o /tmp/vipsbench/out/$1-p.webp[Q=80,strip] &&
        vipsthumbnail "$0" -s 1600 -o /tmp/vipsbench/out/$1-w.webp[Q=78,strip] &&
        vipsthumbnail "$0" -s 400  -o /tmp/vipsbench/out/$1-t.webp[Q=75,strip]'
D_AVIF='vipsthumbnail "$0" -s 1600 -o /tmp/vipsbench/out/$1-w.avif[Q=50,effort=4,strip]'
D_BLUR='vipsthumbnail "$0" -s 32 -o /tmp/vipsbench/out/$1-b.png[strip]'
# The ladder this spike actually recommends: four widths x (AVIF, WebP), plus
# one 2560 JPEG for the guest-tier download zip.
D_FULL='for w in 400 800 1600 2560; do
          vipsthumbnail "$0" -s $w -o /tmp/vipsbench/out/$1-$w.avif[Q=60,effort=4,strip] || exit 1
          vipsthumbnail "$0" -s $w -o /tmp/vipsbench/out/$1-$w.webp[Q=80,strip] || exit 1
        done
        vipsthumbnail "$0" -s 2560 -o /tmp/vipsbench/out/$1-2560.jpg[Q=85,strip]'
D_HASH='sha256sum "$0" > /dev/null && echo $1'

run "3x jpeg"  "$D_JPEG"  || echo "  (jpeg pipeline failed)"
run "3x webp"  "$D_WEBP"  || echo "  (webp pipeline failed)"
run "1x avif 1600" "$D_AVIF" || echo "  (avif unsupported in this libvips build)"
run "blurhash src 32px" "$D_BLUR" || echo "  (blur failed)"
run "sha256 of original" "$D_HASH" || echo "  (hash failed)"
run "full ladder 4w x2 +jpeg" "$D_FULL" || echo "  (full ladder failed)"
