<script lang="ts">
	let { container }: { container: HTMLElement | null } = $props();

	const POINTS_PER_SIDE = 8;
	const VISCOSITY = 20;
	const MOUSE_DIST = 100;
	const DAMPING = 0.15;

	type EdgePoint = {
		pos: number;
		offset: number;
		vOffset: number;
	};

	let canvasEl = $state<HTMLCanvasElement | null>(null);
	let topPoints: EdgePoint[] = [];
	let rightPoints: EdgePoint[] = [];
	let bottomPoints: EdgePoint[] = [];
	let leftPoints: EdgePoint[] = [];

	let mouseX = 0;
	let mouseY = 0;
	let mouseSpeedX = 0;
	let mouseSpeedY = 0;
	let lastMouseX = 0;
	let lastMouseY = 0;
	let rafId: number | null = null;
	let lastW = 0;

	function createSidePoints(length: number): EdgePoint[] {
		const pts: EdgePoint[] = [];
		for (let i = 0; i <= POINTS_PER_SIDE + 1; i++) {
			const t = i / (POINTS_PER_SIDE + 1);
			pts.push({ pos: t * length, offset: 0, vOffset: 0 });
		}
		return pts;
	}

	function moveSidePoints(pts: EdgePoint[], mouseAlong: number, mouseCross: number, speed: number) {
		for (const p of pts) {
			p.vOffset += (0 - p.offset) / VISCOSITY;

			const dAlong = p.pos - mouseAlong;

			if (Math.abs(dAlong) < MOUSE_DIST && Math.abs(mouseCross) < MOUSE_DIST) {
				const influence = (1 - Math.abs(dAlong) / MOUSE_DIST) * (1 - Math.abs(mouseCross) / MOUSE_DIST);
				p.vOffset += (speed / 4) * influence;
			}

			p.vOffset *= 1 - DAMPING;
			p.offset += p.vOffset;
		}
	}

	function drawSide(
		ctx: CanvasRenderingContext2D,
		pts: EdgePoint[],
		getX: (pos: number, offset: number) => number,
		getY: (pos: number, offset: number) => number,
		endX: number,
		endY: number
	) {
		for (let i = 0; i < pts.length; i++) {
			const p = pts[i];
			const px = getX(p.pos, p.offset);
			const py = getY(p.pos, p.offset);
			if (i < pts.length - 1) {
				const n = pts[i + 1];
				const nx = getX(n.pos, n.offset);
				const ny = getY(n.pos, n.offset);
				ctx.quadraticCurveTo(px, py, (px + nx) / 2, (py + ny) / 2);
			} else {
				ctx.lineTo(endX, endY);
			}
		}
	}

	function animate() {
		rafId = requestAnimationFrame(animate);
		if (!container || !canvasEl) return;

		const rect = container.getBoundingClientRect();
		const pad = 50;

		canvasEl.width = rect.width + pad * 2;
		canvasEl.height = rect.height + pad * 2;
		canvasEl.style.left = `${rect.left - pad}px`;
		canvasEl.style.top = `${rect.top - pad}px`;

		const w = rect.width;
		const h = rect.height;

		if (!topPoints.length || Math.abs(lastW - w) > 10) {
			topPoints = createSidePoints(w);
			rightPoints = createSidePoints(h);
			bottomPoints = createSidePoints(w);
			leftPoints = createSidePoints(h);
			lastW = w;
		}

		const relX = mouseX - rect.left;
		const relY = mouseY - rect.top;

		moveSidePoints(topPoints, relX, relY, -mouseSpeedY);
		moveSidePoints(rightPoints, relY, w - relX, mouseSpeedX);
		moveSidePoints(bottomPoints, relX, h - relY, mouseSpeedY);
		moveSidePoints(leftPoints, relY, relX, -mouseSpeedX);

		const ctx = canvasEl.getContext('2d');
		if (!ctx) return;

		ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);

		const ox = pad;
		const oy = pad;

		ctx.beginPath();
		ctx.moveTo(ox, oy);

		drawSide(
			ctx,
			topPoints,
			(pos, _off) => ox + pos,
			(_pos, off) => oy - off,
			ox + w,
			oy
		);

		drawSide(
			ctx,
			rightPoints,
			(_pos, off) => ox + w + off,
			(pos, _off) => oy + pos,
			ox + w,
			oy + h
		);

		drawSide(
			ctx,
			bottomPoints,
			(pos, _off) => ox + w - pos,
			(_pos, off) => oy + h + off,
			ox,
			oy + h
		);

		drawSide(
			ctx,
			leftPoints,
			(_pos, off) => ox - off,
			(pos, _off) => oy + h - pos,
			ox,
			oy
		);

		ctx.closePath();

		const gradient = ctx.createLinearGradient(ox, oy, ox + w, oy + h);
		gradient.addColorStop(0, 'rgba(168, 208, 230, 0.08)');
		gradient.addColorStop(1, 'rgba(168, 208, 230, 0.03)');
		ctx.fillStyle = gradient;
		ctx.fill();

		ctx.strokeStyle = 'rgba(168, 208, 230, 0.15)';
		ctx.lineWidth = 1.5;
		ctx.stroke();
	}

	$effect(() => {
		if (!container) return;

		const controller = new AbortController();

		window.addEventListener(
			'mousemove',
			(e: MouseEvent) => {
				mouseX = e.clientX;
				mouseY = e.clientY;
			},
			{ signal: controller.signal }
		);

		const speedInterval = setInterval(() => {
			mouseSpeedX = mouseX - lastMouseX;
			mouseSpeedY = mouseY - lastMouseY;
			lastMouseX = mouseX;
			lastMouseY = mouseY;
		}, 50);

		rafId = requestAnimationFrame(animate);

		return () => {
			controller.abort();
			clearInterval(speedInterval);
			if (rafId) cancelAnimationFrame(rafId);
		};
	});
</script>

<canvas bind:this={canvasEl} class="pointer-events-none fixed z-20" aria-hidden="true"></canvas>
