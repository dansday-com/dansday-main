function esc(s: unknown): string {
	if (s == null) return '';
	const str = String(s);
	return str.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n');
}

type Exp = { title?: unknown; period?: unknown; description?: unknown };
type Service = { title?: unknown; description?: unknown };
type Skill = { title?: unknown };
type Testimonial = { name?: unknown; company?: unknown; description?: unknown };

export function experienceToCodeLines(edu: Exp[], emp: Exp[]): string[] {
	const lines: string[] = ['const experience = {'];
	if (edu.length > 0) {
		lines.push('  education: [');
		for (const e of edu) {
			if (e.description) {
				lines.push(`    { school: "${esc(e.title)}", period: "${esc(e.period)}", description: "${esc(e.description)}" },`);
			} else {
				lines.push(`    { school: "${esc(e.title)}", period: "${esc(e.period)}" },`);
			}
		}
		lines.push('  ],');
	}
	if (emp.length > 0) {
		lines.push('  employment: [');
		for (const e of emp) {
			if (e.description) {
				lines.push(`    { company: "${esc(e.title)}", period: "${esc(e.period)}", description: "${esc(e.description)}" },`);
			} else {
				lines.push(`    { company: "${esc(e.title)}", period: "${esc(e.period)}" },`);
			}
		}
		lines.push('  ],');
	}
	lines.push('};');
	return lines;
}

export function servicesToCodeLines(services: Service[]): string[] {
	const lines: string[] = ['const services = ['];
	for (const s of services) {
		lines.push(`  { title: "${esc(s.title)}", description: "${esc(s.description)}" },`);
	}
	lines.push('];');
	return lines;
}

export function skillsToCodeLines(design: Skill[], development: Skill[]): string[] {
	const lines: string[] = ['const skills = {'];
	if (design.length > 0) {
		lines.push('  design: [');
		for (const s of design) {
			lines.push(`    { title: "${esc(s.title)}" },`);
		}
		lines.push('  ],');
	}
	if (development.length > 0) {
		lines.push('  development: [');
		for (const s of development) {
			lines.push(`    { title: "${esc(s.title)}" },`);
		}
		lines.push('  ],');
	}
	lines.push('};');
	return lines;
}

export function testimonialsToCodeLines(testimonials: Testimonial[]): string[] {
	const lines: string[] = ['const testimonials = ['];
	for (const t of testimonials) {
		lines.push(`  { name: "${esc(t.name)}", company: "${esc(t.company)}", description: "${esc(t.description)}" },`);
	}
	lines.push('];');
	return lines;
}
