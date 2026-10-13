const playerInstance = jwplayer("player").setup({
  controls: true,
  sharing: true,
  displaytitle: true,
  displaydescription: true,
  abouttext: "Buy Me a Coffee",
  aboutlink: "https://www.buymeacoffee.com/pingo",

  skin: {
    name: "netflix"
  },

  logo: {
    file: "",
    link: ""
  },

  captions: {
    color: "#FFF",
    fontSize: 14,
    backgroundOpacity: 0,
    edgeStyle: "raised"
  },

  playlist: [
    {
      sources: [
        {
          file: "https://hstorage.xyz/files/S/shiofuki-mermaid/shiofuki-mermaid-1.mp4",
          type: "mp4",
          default: true
        }
      ],
    }
  ],

  advertising: {
    client: "vast",
    schedule: [
      {
        offset: "pre",
        tag: "https://s.magsrv.com/v1/vast.php?idzone=5499622"
      }
    ]
  }
});

playerInstance.on("ready", function () {
  const buttonId = "download-video-button";
  const iconPath =
    "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgd2lkdGg9IjY0IiBoZWlnaHQ9IjY0Ij48cGF0aCBmaWxsPSJub25lIiBkPSJNMCAwaDI0djI0SDB6Ii8+PHBhdGggZD0iTTMgMTloMTh2Mkgzdi0yem0xMC01LjgyOEwxOS4wNzEgNy4xbDEuNDE0IDEuNDE0TDEyIDE3IDMuNTE1IDguNTE1IDQuOTI5IDcuMSAxMSAxMy4xN1YyaDJ2MTEuMTcyeiIgZmlsbD0icmdiYSgyNDcsMjQ3LDI0NywxKSIvPjwvc3ZnPg==";
  const tooltipText = "Download Video";

  playerInstance.addButton(iconPath, tooltipText, buttonClickAction, buttonId);

  function buttonClickAction() {
    const playlistItem = playerInstance.getPlaylistItem();
    const anchor = document.createElement("a");
    const fileUrl = playlistItem.sources[0].file;
    anchor.href = fileUrl;
    anchor.download = "video.mp4";
    anchor.style.display = "none";
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
  }

  const playerContainer = playerInstance.getContainer();
  const buttonContainer = playerContainer.querySelector(".jw-button-container");

  playerInstance.on("adBlock", () => {
    const modal = document.querySelector("div.modal");
    modal.style.display = "flex";
    document.getElementById("close")
      .addEventListener("click", () => location.reload());
  });

  const rewindContainer = playerContainer.querySelector(".jw-display-icon-rewind");
  const forwardContainer = rewindContainer.cloneNode(true);
  const forwardDisplayButton = forwardContainer.querySelector(".jw-icon-rewind");
  forwardDisplayButton.style.transform = "scaleX(-1)";
  forwardDisplayButton.ariaLabel = "Forward 10 Seconds";
  const nextContainer = playerContainer.querySelector(".jw-display-icon-next");
  nextContainer.parentNode.insertBefore(forwardContainer, nextContainer);

  playerContainer.querySelector(".jw-display-icon-next").style.display = "none";

  const rewindControlBarButton = buttonContainer.querySelector(".jw-icon-rewind");
  const forwardControlBarButton = rewindControlBarButton.cloneNode(true);
  forwardControlBarButton.style.transform = "scaleX(-1)";
  forwardControlBarButton.ariaLabel = "Forward 10 Seconds";
  rewindControlBarButton.parentNode.insertBefore(
    forwardControlBarButton,
    rewindControlBarButton.nextElementSibling
  );

  [forwardDisplayButton, forwardControlBarButton].forEach((button) => {
    button.onclick = () => {
      playerInstance.seek(playerInstance.getPosition() + 10);
    };
  });
});