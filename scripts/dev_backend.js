const { spawn } = require("child_process");
const fs = require("fs");
const path = require("path");

function resolvePhpExecutable() {
  if (process.env.PHP_PATH) return process.env.PHP_PATH;

  const candidates = process.platform === "win32"
    ? ["C:\\xampp\\php\\php.exe", "php"]
    : ["php"];

  for (const candidate of candidates) {
    if (candidate === "php" || fs.existsSync(candidate)) return candidate;
  }
  return "php";
}

const projectRoot = path.resolve(__dirname, "..");
const backendDir = path.join(projectRoot, "backend");
const phpExecutable = resolvePhpExecutable();

const args = ["-S", "127.0.0.1:8000", "router.php"];

console.log(`[backend] Starting with ${phpExecutable} ${args.join(" ")}`);

const child = spawn(phpExecutable, args, {
  cwd: backendDir,
  stdio: "inherit",
  shell: false,
});

child.on("error", (err) => {
  console.error(`[backend] Failed to start: ${err.message}`);
  process.exit(1);
});

child.on("exit", (code) => {
  process.exit(code ?? 0);
});

