import WebViewer from "@pdftron/webviewer";
import moment from "moment";
import sweetalert from "@/mixins/sweetalert.js";
import request from "@/mixins/request.js";
import html2pdf from "html2pdf.js";
import { showLoader } from "./helpers.mixin";
import axios from "axios";

export default {
  mixins: [sweetalert, request],
  methods: {
    /**
     *
     * @param {*} element Element inner html of the pdf content
     * @param {*} filename Name of the generated file
     * @param {*} fileHandler  The callback function to handle the generated file
     * @param {*} returnType Defaults to blob. Options include buffer, file, and blob
     * @param {*} orientation Defaults to landscape. Options include landscape, portrait
     */
    genPDF(
      element,
      filename,
      fileHandler,
      returnType = "buffer",
      orientation = "landscape"
    ) {
      html2pdf()
        .set({
          margin: [20, 5, 5, 5],
          filename: filename,
          image: {
            type: "jpeg",
            quality: 1.0,
          },
          html2canvas: {
            scale: 6,
          },
          jsPDF: {
            format: "A4",
            orientation: orientation,
          },
        })
        .from(element, "string")
        .outputPdf("arraybuffer")
        .then((result) => {
          if (typeof fileHandler === "function") {
            if (returnType === "file") {
              const file = this.generateFileFromBuffer(result, filename);
              fileHandler(file);
            }

            if (returnType === "buffer") {
              fileHandler(result);
            }

            if (returnType === "blob") {
              const blob = new Blob([result], {
                type: "application/pdf",
              });

              fileHandler(blob);
            }
          }
        });
    },

    signPDF(
      pdfUrl,
      uid,
      session,
      position,
      btnElementId,
      expectedSignatures,
      submitHandler
    ) {
      showLoader("loading file...");

      WebViewer(
        {
          path: "/webviewer",
          initialDoc: pdfUrl,
        },
        document.getElementById(uid)
      ).then(async (instance) => {
        const { UI, Core } = instance;
        const { documentViewer, annotationManager, Tools, Annotations } = Core;

        UI.disableElements(["toolbarGroup-Shapes"]);
        UI.disableElements(["toolbarGroup-Edit"]);
        UI.disableElements(["toolbarGroup-Insert"]);
        UI.disableElements(["toolbarGroup-Annotate"]);
        UI.disableElements(["toolbarGroup-Forms"]);
        UI.setToolbarGroup("toolbarGroup-FillAndSign");
        const signatureTool = documentViewer.getTool(
          "AnnotationCreateSignature"
        );

        documentViewer.addEventListener("documentLoaded", () => {
          let stampCanvas = document
            .getElementById("signature-content-" + uid)
            .getContext("2d");

          stampCanvas.beginPath();
          stampCanvas.lineWidth = "1";
          stampCanvas.strokeStyle = "blue";
          stampCanvas.rect(0, 0, 200, 115);
          stampCanvas.stroke();

          let unicefImage = new Image();

          axios.get(`/api/get-user-signature?user_id=${session.id}`).then(
            (response) => {
              if (response.data.signature != null) {
                unicefImage.src = response.data.signature;

                stampCanvas.font = "normal 10px Arial";
                stampCanvas.fillStyle = "blue";
                stampCanvas.textAlign = "center";
                stampCanvas.fillText(session.name, 100, 45);
                stampCanvas.fillText(position, 100, 60);
                stampCanvas.fillText("UNICEF SSD", 100, 80);
                stampCanvas.fillText(moment().format("DD/MMM/YYYY"), 100, 98);

                unicefImage.onload = function () {
                  stampCanvas.drawImage(unicefImage, 72.5, 15, 55, 50);
                };

                setTimeout(function () {
                  let image = document
                    .getElementById("signature-content-" + uid)
                    .toDataURL("image/png", 1.0);
                  signatureTool.importSignatures([image]);
                }, 2000);

                showLoader("", true);
              } else {
                unicefImage.src = "/images/unicef.logo.blue.png";
              }
            },
            (e) => {
              console.log("error fetching signatures", e);
            }
          );
        });

        const proceedBtn = document.getElementById(btnElementId);

        proceedBtn.addEventListener("click", async function () {
          const doc = documentViewer.getDocument();
          const xfdfString = await annotationManager.exportAnnotations();
          const data = await doc.getFileData({ xfdfString });
          const arr = new Uint8Array(data);
          const blob = new Blob([arr], { type: "application/pdf" });

          if (typeof submitHandler === "function") {
            submitHandler(blob);
          }

          //checking signatures
          // const parser = new DOMParser();
          // const xmlDoc = parser.parseFromString(xfdfString, "text/xml");
          // const imageDataTags = xmlDoc.getElementsByTagName("imagedata");
          // const inklistTags = xmlDoc.getElementsByTagName("inklist");
          //
          // let hasSigned = false;
          //
          // let foundSignatures = imageDataTags.length + inklistTags.length;
          //
          // if (foundSignatures === expectedSignatures) {
          //   hasSigned = true;
          // }
          //
          // if (!hasSigned) {
          //   if (typeof submitHandler === "function") {
          //     submitHandler(false);
          //   }
          // } else {
          //   if (typeof submitHandler === "function") {
          //     submitHandler(blob);
          //   }
          // }
        });
      });
    },
    viewPDF(pdfUrl, uid) {
      showLoader("loading file...");

      WebViewer(
        {
          path: "/webviewer",
          initialDoc: pdfUrl,
        },
        document.getElementById(uid)
      ).then((instance) => {
        const { UI } = instance;

        UI.disableElements(["toolbarGroup-Shapes"]);
        UI.disableElements(["toolbarGroup-Edit"]);
        UI.disableElements(["toolbarGroup-Insert"]);
        UI.disableElements(["toolbarGroup-Annotate"]);
        UI.disableElements(["toolbarGroup-Forms"]);

        UI.disableElements(["toolbarGroup-FillAndSign"]);
        UI.setToolbarGroup("toolbarGroup-View");

        showLoader("", true);
      });

      showLoader("", true);
    },

    generateFileFromBlob(blob, filename) {
      const file = new File([blob], filename, {
        type: "application/pdf",
        lastModified: new Date(),
      });

      return file;
    },

    generateFileFromBuffer(arrayBuffer, filename) {
      const blob = new Blob([arrayBuffer], {
        type: "application/pdf",
      });

      const file = new File([blob], filename, {
        type: "application/pdf",
        lastModified: new Date(),
      });

      return file;
    },
  },
};
