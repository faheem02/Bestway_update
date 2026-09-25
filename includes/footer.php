      </div>
      <!-- /.content -->

      <!-- Footer -->
      <footer class="sticky-footer">
        <div class="container">
          <div class="copyright text-center">
            &copy; <?= date('Y') ?> Bestway Distribution. All rights reserved.
          </div>
        </div>
      </footer>
    </div>
    <!-- End of Content Wrapper -->
  </div>
  <!-- End of Wrapper -->

  <!-- Scroll to Top -->
  <a class="scroll-to-top" href="#page-top" id="scrollToTop">
    <i class="fas fa-angle-up"></i>
  </a>

  <!-- Bootstrap 4.6.2 Bundle JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/js/bootstrap.bundle.min.js"></script>

  <!-- Flatpickr Date Picker JS -->
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      flatpickr('.datepicker', { dateFormat: 'Y-m-d', allowInput: true });
    });
  </script>

  <!-- Custom Main JS -->
  <script src="<?= BASE_URL ?>assets/js/main.js?v=3"></script>
</body>
</html>
