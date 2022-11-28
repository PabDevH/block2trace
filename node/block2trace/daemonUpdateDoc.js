var daemon = require("daemonize2").setup({
    main: "processUpdateDoc.js",
    name: "b2t_UpdateDoc",
    pidfile: "b2t_UpdateDoc.pid"
});

switch (process.argv[2]) {

    case "start":
        daemon.start();
        break;

    case "stop":
        daemon.stop();
        break;

    default:
        console.log("Usage: [start|stop]");
}