# Diagnostic ciblé de l’onglet vertical « raconte nous »

La recherche du dépôt ne trouve aucun texte `raconte nous`, aucun `writing-mode` et aucun
widget latéral correspondant dans le plugin UFSC. Aucun sélecteur de masquage approximatif
n’est donc ajouté. Sur la DEV authentifiée, ouvrir la page du portail puis exécuter cette
commande dans la console :

```js
[...document.querySelectorAll('a,button,[role="button"],iframe')]
  .filter(el => /raconte[\s_-]*nous/i.test(`${el.innerText} ${el.getAttribute('aria-label') || ''}`))
  .map(el => ({tag: el.tagName, id: el.id, classes: [...el.classList], href: el.href || '',
    styles: ['position','writing-mode','transform','left','right','z-index'].reduce((o,k) =>
      (o[k] = getComputedStyle(el).getPropertyValue(k), o), {}), node: el}));
```

Dans DevTools, utiliser ensuite **Event Listeners** et l’onglet **Sources** du nœud retourné
pour relever le script, la feuille CSS, le thème/widget et l’initiateur. Le correctif devra
viser uniquement l’identifiant exact retourné, dans `.ufsc-club-portal`. Ce diagnostic est
désactivé par défaut et n’est chargé par aucun hook WordPress.
