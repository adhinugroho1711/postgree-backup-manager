                    </div>
                    <!-- End of Content Row -->
                </div>
                <!-- End of Page Content -->

                <!-- Footer -->
                <footer class="footer mt-auto py-3 bg-white border-top">
                    <div class="container-fluid">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                            <div class="mb-2 mb-md-0">
                                <span class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</span>
                            </div>
                            <div>
                                <span class="text-muted">Versi 1.0.0</span>
                            </div>
                        </div>
                    </div>
                </footer>
                <!-- End of Footer -->
            </div>
        </div>
    </div>

    <!-- Custom scripts -->
    <script>
        $(document).ready(function() {
            // Enable tooltips - Bootstrap sudah dimuat di header
            try {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            } catch (e) {
                console.error('Error initializing tooltips:', e);
            }
            
            // Toggle the side navigation
            $('#sidebarToggleTop').on('click', function(e) {
                e.preventDefault();
                $('body').toggleClass('sidebar-toggled');
                $('.sidebar').toggleClass('toggled');
                
                if ($('.sidebar .collapse').hasClass('show')) {
                    $('.sidebar .collapse').removeClass('show');
                }
            });
            
            // Close any open menu accordions when window is resized below 768px
            $(window).on('resize', function() {
                if ($(window).width() < 768) {
                    var $openedSubmenu = $('.sidebar .collapse.show');
                    if ($openedSubmenu.length) {
                        var bsCollapse = new bootstrap.Collapse($openedSubmenu[0], {
                            toggle: false
                        });
                        bsCollapse.hide();
                    }
                }
            });
            
            // Toggle the side navigation when window is resized below 480px
            if ($(window).width() < 480 && !$('.sidebar').hasClass('toggled')) {
                $('body').addClass('sidebar-toggled');
                $('.sidebar').addClass('toggled');
                
                var $openedSubmenu = $('.sidebar .collapse.show');
                if ($openedSubmenu.length) {
                    var bsCollapse = new bootstrap.Collapse($openedSubmenu[0], {
                        toggle: false
                    });
                    bsCollapse.hide();
                }
            }
        });
        
        // Prevent the content wrapper from scrolling when the fixed side navigation hovered over
        $('.sidebar').on('mousewheel', function(e) {
            if (this.scrollTop === 0 && e.deltaY < 0) {
                e.preventDefault();
            } else if (this.scrollHeight === this.scrollTop + this.offsetHeight && e.deltaY > 0) {
                e.preventDefault();
            }
        });
    </script>
    
    <?php if (isset($custom_scripts)): ?>
        <?php echo $custom_scripts; ?>
    <?php endif; ?>
</body>
</html>
