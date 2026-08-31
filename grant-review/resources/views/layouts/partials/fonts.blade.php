{{--
    Critical head hints to prevent FOUC:
    - Google Fonts loaded via <link> tags so the font CSS downloads in parallel
      with the Vite stylesheet instead of serially after it.
    - Inline SVG icons (heroicons + hand-written) rely on Tailwind w-/h- classes
      for sizing and ship without width/height attributes, so before the CSS
      arrives the browser renders them at the default 300x150 replaced-element
      size. This critical <style> gives SVGs a 1em baseline so they flash at a
      reasonable size instead of giant. :where() keeps specificity at zero so
      any Tailwind .w-*/.h-* class still overrides it once CSS loads.
    Keep the font families in sync with tailwind.config.js fontFamily.
--}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,600&family=Fira+Code:wght@400;500;600;700&display=swap">
<style>
    svg:where(:not([width]):not([height])) { width: 1em; height: 1em; }
</style>
