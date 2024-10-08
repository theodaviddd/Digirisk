/**
 * Initialise l'objet "digiai" ainsi que la méthode "init" obligatoire pour la bibliothèque DigiriskDolibarr.
 *
 * @since   8.5.0
 * @version 8.5.0
 */
window.digiriskdolibarr.digiai = {};

/**
 * La méthode appelée automatiquement par la bibliothèque DigiriskDolibarr.
 *
 * @since   8.5.0
 * @version 8.5.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.init = function() {
  window.digiriskdolibarr.digiai.event();
};

/**
 * La méthode contenant tous les événements pour les digiais.
 *
 * @since   8.5.0
 * @version 8.5.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.event = function() {
  $(document).on('change', '#image_file', window.digiriskdolibarr.digiai.submitForm);
};

/**
 * Méthode pour gérer le formulaire de soumission du fichier image et l'analyse par Google Vision et ChatGPT.
 *
 * @since   8.5.0
 * @version 8.5.0
 *
 * @return {void}
 */
window.digiriskdolibarr.digiai.submitForm = async function(e) {
  e.preventDefault();

  let token = window.saturne.toolbox.getToken()
  let dolUrlRoot = $('#dol_url_root').val()

  // Récupération du fichier
  let imageFile = document.getElementById('image_file').files[0];
  if (!imageFile) {
    alert('Veuillez sélectionner une image.');
    return;
  }

  // Affichage de la modal
  $('#digiai_modal').addClass('modal-active');
  let modalContent = $('#digiai_modal .modal-content');
  modalContent.append('<div class="analysis-in-progress"></div>');

  // Création du FormData pour l'image
  let formData = new FormData();
  formData.append('image_file', imageFile);

  try {
    // Étape 1: Upload de l'image à Google Vision
    modalContent.find('.analysis-in-progress').append(`
            <span>En attente de la réponse de Google Vision...</span>
            <div class="loader" style="display:inline-block; width:16px; height:16px; border:2px solid #ccc; border-top:2px solid #4CAF50; border-radius:50%; animation: spin 1s linear infinite;"></div>
            <br>
        `);


    let visionResponse = await fetch('backend_endpoint_for_google_vision.php?token=' + token, {
      method: 'POST',
      body: formData
    });

    if (!visionResponse.ok) {
      throw new Error('Failed to fetch Google Vision');
    }

    let visionData = await visionResponse.json();
    let description = generateDescriptionFromGoogleVision(visionData);

    // Étape 2: Envoi des résultats de Google Vision à ChatGPT
    modalContent.find('.analysis-in-progress').append(`
            <span>En attente de la réponse de ChatGPT...</span>
            <div class="loader" style="display:inline-block; width:16px; height:16px; border:2px solid #ccc; border-top:2px solid #4CAF50; border-radius:50%; animation: spin 1s linear infinite;"></div>
            <br>
        `);

    let chatGptResponse = await fetch('backend_endpoint_for_chatgpt.php?token=' + token, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ description })
    });

    if (!chatGptResponse.ok) {
      throw new Error('Failed to fetch ChatGPT');
    }

    let chatGptData = await chatGptResponse.json();

    // Suppression des loaders après les étapes
    modalContent.find('.analysis-in-progress').empty();

    // Traitement des résultats de ChatGPT
    let table = $('#risque_table');
    table.attr('style', 'width: 100%; border-collapse: collapse; border: 1px solid #ccc; display: table;');
    let tbody = table.find('tbody');
    tbody.empty();

    let risque = JSON.parse(chatGptData.choices[0].message.content);

    // Boucle pour générer les lignes du tableau
    risque.forEach(risque => {
      let tr = $('<tr class="oddeven">');
      let title = risque.title;
      let cotation = risque.cotation;
      let description = risque.description;
      let prevention_actions = risque.prevention_actions;

      let cotationScale = 0;
      cotation = parseInt(cotation);
      if (cotation < 48) {
        cotationScale = 1;
      } else if (cotation < 51) {
        cotationScale = 2;
      } else if (cotation < 80) {
        cotationScale = 3;
      } else {
        cotationScale = 4;
      }

      let cotationContainer = $('<div>').addClass('risk-evaluation-cotation').attr('value', cotationScale).attr('data-scale', cotationScale).text(cotation);

      // Créer un conteneur d'image
      let riskImgContainer = $('<img>').addClass('danger-category-pic tooltip wpeo-tooltip-event hover')
        .attr('src', dolUrlRoot + '/custom/digiriskdolibarr/img/categorieDangers/' + title + '.png');

      tr.append($('<td>').append(riskImgContainer));
      tr.append($('<td>').append(cotationContainer));
      tr.append($('<td>').text(description));
      let actions = $('<ul>');
      prevention_actions.forEach(action => {
        actions.append($('<li>').text('- ' + action));
      });
      tr.append($('<td>').append(actions));
      tr.append($('<button class="wpeo-button">Ajouter</button>'));
      tbody.append(tr);
    });
  } catch (error) {
    modalContent.find('.analysis-in-progress').empty().append('An error occurred: ' + error.message);
  }
};

/**
 * Fonction pour générer la description de Google Vision
 *
 * @param visionData
 * @return {string}
 */
function generateDescriptionFromGoogleVision(visionData) {
  let labels = visionData.responses[0].labelAnnotations;
  let localizedObjects = visionData.responses[0].localizedObjectAnnotations;

  let description = "Voici les éléments détectés dans l'image :\n\n";
  labels.forEach(label => {
    description += `- ${label.description} (Confiance: ${(label.score * 100).toFixed(2)}%)\n`;
  });
  localizedObjects.forEach(object => {
    description += `- ${object.name} (Confiance: ${(object.score * 100).toFixed(2)}%)\n`;
  });
  return description;
}

// Ajout du CSS pour l'animation du loader
const style = document.createElement('style');
style.innerHTML = `
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
`;
document.head.appendChild(style);
