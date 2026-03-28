const { spawn } = require("child_process");
const path = require("path");

const isWin = process.platform === "win32";
const projectRoot = path.resolve(__dirname, "..");

function spawnNamed(name, command, args, options = {}) {
  const { useShell = isWin, ...restOptions } = options;
  const child = spawn(command, args, {
    cwd: projectRoot,
    shell: useShell,
    ...restOptions,
  });

  child.stdout && child.stdout.on("data", (data) => process.stdout.write(`[${name}] ${data}`));
  child.stderr && child.stderr.on("data", (data) => process.stderr.write(`[${name}] ${data}`));
  child.on("error", (err) => {
    console.error(`[${name}] Failed to start: ${err.message}`);
  });

  return child;
}

const backend = spawnNamed("backend", process.execPath, [path.join("scripts", "dev_backend.js")], {
  stdio: ["inherit", "pipe", "pipe"],
  useShell: false,
});

const frontend = isWin
  ? spawnNamed(
      "frontend",
      "cmd.exe",
      ["/d", "/s", "/c", "npm run dev -- --host 0.0.0.0 --port 5173"],
      {
        cwd: path.join(projectRoot, "web frontend"),
        stdio: ["inherit", "pipe", "pipe"],
        useShell: false,
      }
    )
  : spawnNamed(
      "frontend",
      "npm",
      ["run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"],
      {
        cwd: path.join(projectRoot, "web frontend"),
        stdio: ["inherit", "pipe", "pipe"],
        useShell: false,
      }
    );

let shuttingDown = false;

function shutdown(signal) {
  if (shuttingDown) return;
  shuttingDown = true;
  console.log(`\n[dev] Received ${signal}. Stopping child processes...`);

  for (const proc of [backend, frontend]) {
    if (proc && !proc.killed) {
      try {
        proc.kill("SIGTERM");
      } catch (_) {}
    }
  }

  setTimeout(() => process.exit(0), 500);
}

process.on("SIGINT", () => shutdown("SIGINT"));
process.on("SIGTERM", () => shutdown("SIGTERM"));

backend.on("exit", (code) => {
  if (!shuttingDown) {
    console.log(`[backend] exited with code ${code ?? 0}`);
    shutdown("backend_exit");
  }
});

frontend.on("exit", (code) => {
  if (!shuttingDown) {
    console.log(`[frontend] exited with code ${code ?? 0}`);
    shutdown("frontend_exit");
  }
});
