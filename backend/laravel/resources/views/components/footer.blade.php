<footer class="content-footer footer bg-footer-theme">
    <div class="container-xxl d-flex justify-content-center align-items-center py-2">
        <div class="text-center">
            © <span id="current-year"></span>, made with ❤️ by
          <a href="https://www.instagram.com/as.adrosyadi1/" class="footer-link fw-medium">AR Production</a>
        </div>
      </div>

      <script>
        var yearElement = document.getElementById('current-year');
        var currentYear = new Date().getFullYear();
        yearElement.textContent = currentYear;
      </script>
</footer>
